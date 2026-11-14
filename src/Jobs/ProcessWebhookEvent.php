<?php

namespace Noop\Eupago\Jobs;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Noop\Eupago\Contracts\HandlesEupagoPayments;
use Noop\Eupago\Data\ConfirmedPayment;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Events\PaymentAttemptExpired;
use Noop\Eupago\Events\PaymentConfirmed;
use Noop\Eupago\Events\PaymentNeedsReview;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;
use RuntimeException;
use Throwable;

/**
 * Applies one stored webhook event to its payment attempt. Only pending events are processed, so
 * a repeated run (a retry, or a dispatch from eupago:recover) changes nothing; to process an event
 * again, set it back to pending first.
 *
 * handle() is also the entry point without a queue (the fake, a manual reprocess): there release()
 * does nothing, and a handler exception is thrown to the caller after it is stored on the event.
 */
class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public PaymentWebhookEvent $event) {}

    public function handle(): void
    {
        $event = $this->event->fresh();

        if ($event === null || $event->status !== EventStatus::Pending) {
            return;
        }

        $attempt = PaymentAttempt::query()->where('identifier', $event->identifier)->first();

        if ($attempt === null) {
            $event->update(['status' => EventStatus::Orphaned, 'processed_at' => now()]);

            return;
        }

        if ($attempt->status === AttemptStatus::Creating) {
            // The request that created the attempt has not finished yet.
            $this->release(30);

            return;
        }

        try {
            DB::transaction(fn () => $this->process($event->id, $attempt->id));
        } catch (Throwable $e) {
            // Only while still pending: a concurrent duplicate job may have processed it meanwhile.
            PaymentWebhookEvent::query()->whereKey($event->id)->where('status', EventStatus::Pending)
                ->update(['error_message' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * The transaction of a webhook body: under "transaction" (official plugin) or "transactions"
     * (documentation), with a string identifier and status; null otherwise.
     *
     * @param  array<mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function transaction(array $payload): ?array
    {
        $transaction = $payload['transaction'] ?? $payload['transactions'] ?? null;

        if (! is_array($transaction) || ! is_string($transaction['identifier'] ?? null) || ! is_string($transaction['status'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $transaction */
        return $transaction;
    }

    /**
     * Closes an attempt without a payment. Call it inside a transaction, with the attempt locked.
     */
    public static function close(PaymentAttempt $attempt, AttemptStatus $status): void
    {
        $attempt->update(['status' => $status]);
        self::handler()->expired($attempt);
        event(new PaymentAttemptExpired($attempt));
    }

    private function process(int $eventId, int $attemptId): void
    {
        // Always lock the event before the attempt.
        $event = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($eventId);

        if ($event->status !== EventStatus::Pending) {
            return;
        }

        $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attemptId);
        $transaction = self::transaction($event->payload) ?? [];
        $status = strtoupper((string) ($transaction['status'] ?? ''));

        [$outcome, $note] = match ($status) {
            'PAID' => $this->paid($attempt, $transaction),
            'EXPIRED', 'CANCELED', 'CANCEL' => $this->closeIfOpen($attempt, AttemptStatus::Expired),
            'ERROR' => $this->closeIfOpen($attempt, AttemptStatus::Failed),
            default => [EventStatus::Processed, 'ignored'],
        };

        $event->update([
            'payment_attempt_id' => $attempt->id,
            'status' => $outcome,
            'error_message' => $note,
            'processed_at' => now(),
        ]);

        if ($status === 'PAID' && $outcome === EventStatus::Rejected) {
            // Money may have been received: tell someone. Only the event id and the generic reason.
            report(new RuntimeException("EuPago webhook event {$event->id} was rejected: {$note}"));
        }
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @return array{EventStatus, string|null}
     */
    private function paid(PaymentAttempt $attempt, array $transaction): array
    {
        $amount = is_array($transaction['amount'] ?? null) ? $transaction['amount']['value'] ?? null : null;
        $reference = self::compact($transaction['reference'] ?? null);
        $entity = self::compact($transaction['entity'] ?? null);
        $trid = self::string($transaction['trid'] ?? null);

        if (self::cents($amount) !== $attempt->amount_cents) {
            return [EventStatus::Rejected, 'The paid amount does not match the attempt.'];
        }

        if ($reference !== null && $attempt->reference !== null && $reference !== self::compact($attempt->reference)) {
            return [EventStatus::Rejected, 'The reference does not match the attempt.'];
        }

        if ($entity !== null && $attempt->entity !== null && $entity !== self::compact($attempt->entity)) {
            return [EventStatus::Rejected, 'The entity does not match the attempt.'];
        }

        if ($attempt->status === AttemptStatus::Paid) {
            return [EventStatus::Processed, $trid !== null && $trid !== $attempt->trid ? 'The attempt was already paid with a different transaction.' : null];
        }

        $attempt->reference ??= $reference;
        $attempt->entity ??= $entity;
        $reference = $attempt->reference;

        if ($reference === null) {
            return [EventStatus::Rejected, 'The payment has no reference.'];
        }

        $paidAt = self::paidAt($transaction['date'] ?? null);
        $attempt->fill(['status' => AttemptStatus::Paid, 'trid' => $trid, 'paid_at' => $paidAt])->save();

        $handler = self::handler();

        if ($handler->canReceive($attempt)) {
            $handler->received($attempt, new ConfirmedPayment($attempt->amount_cents, $trid ?? $attempt->identifier, $attempt->method, $reference, $attempt->entity, $paidAt));
            event(new PaymentConfirmed($attempt));
        } else {
            event(new PaymentNeedsReview($attempt));
        }

        return [EventStatus::Processed, null];
    }

    /**
     * @return array{EventStatus, null}
     */
    private function closeIfOpen(PaymentAttempt $attempt, AttemptStatus $status): array
    {
        if (in_array($attempt->status, [AttemptStatus::Pending, AttemptStatus::Unknown], true)) {
            self::close($attempt, $status);
        }

        return [EventStatus::Processed, null];
    }

    private static function handler(): HandlesEupagoPayments
    {
        if (! app()->bound(HandlesEupagoPayments::class)) {
            throw new RuntimeException('Bind an implementation of '.HandlesEupagoPayments::class.' in the container to process EuPago payments.');
        }

        return app(HandlesEupagoPayments::class);
    }

    /**
     * An amount in euros ("12.34", 12.34, 12) as cents, without float arithmetic; null when it is
     * not a non-negative amount with at most two significant decimals.
     */
    private static function cents(mixed $value): ?int
    {
        $value = is_int($value) || is_float($value) ? (string) $value : $value;

        if (! is_string($value) || ! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }

        $decimals = $matches[2] ?? '';

        if (rtrim(substr($decimals, 2), '0') !== '') {
            return null;
        }

        return (int) $matches[1] * 100 + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }

    private static function string(mixed $value): ?string
    {
        return (is_string($value) || is_int($value)) && $value !== '' ? (string) $value : null;
    }

    /**
     * A reference or entity without whitespace, so "123 456 789" matches "123456789".
     */
    private static function compact(mixed $value): ?string
    {
        $value = self::string($value);

        return $value === null ? null : (preg_replace('/\s+/', '', $value) ?: null);
    }

    /**
     * The event's date is in UTC. Eloquent stores a date as formatted, so convert it to the app's timezone.
     */
    private static function paidAt(mixed $date): CarbonImmutable
    {
        if (is_string($date) && $date !== '') {
            try {
                return CarbonImmutable::parse($date, 'UTC')->setTimezone(now()->getTimezone());
            } catch (InvalidFormatException) {
                // Fall back to now.
            }
        }

        return CarbonImmutable::now();
    }
}
