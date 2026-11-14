<?php

namespace Noop\Eupago;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\FailureType;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Exceptions\EupagoException;
use Noop\Eupago\Models\PaymentAttempt;
use SensitiveParameter;
use Throwable;

/**
 * Requests Multibanco references and MB WAY payments for a payable. Returns the attempt only once it
 * is pending; otherwise throws an EupagoException that says whether it is safe to try again.
 */
class Eupago
{
    public function __construct(private readonly EupagoClient $client) {}

    public function multibanco(Model $payable, int $amountCents): PaymentAttempt
    {
        $this->validate($amountCents);

        $until = now('Europe/Lisbon')->addDays((int) config('eupago.multibanco_days'))->endOfDay();

        return $this->request(
            $payable,
            PaymentMethod::Multibanco,
            $amountCents,
            // Eloquent stores a date as formatted, without converting it: store it in the app's timezone.
            $until->copy()->setTimezone(now()->getTimezone()),
            fn (PaymentAttempt $attempt) => $this->client->multibanco($attempt->identifier, $amountCents, $until),
        );
    }

    public function mbway(Model $payable, int $amountCents, #[SensitiveParameter] string $phone): PaymentAttempt
    {
        $this->validate($amountCents);

        if (! preg_match('/^(?:(?:\+|00)?351)?(\d{9})$/', (string) preg_replace('/\s+/', '', $phone), $matches)) {
            throw new EupagoException('The phone number is not a valid Portuguese number.', FailureType::Rejected);
        }

        $phone = $matches[1];

        return $this->request(
            $payable,
            PaymentMethod::Mbway,
            $amountCents,
            now()->addMinutes((int) config('eupago.mbway_minutes')),
            fn (PaymentAttempt $attempt) => $this->client->mbway($attempt->identifier, $amountCents, $phone),
        );
    }

    private function validate(int $amountCents): void
    {
        $this->client->assertConfigured();

        if ($amountCents <= 0) {
            throw new EupagoException('The amount must be greater than zero.', FailureType::Rejected);
        }
    }

    /**
     * @param  Closure(PaymentAttempt): array{entity: string|null, reference: string, transaction_id: string|null}  $send
     */
    private function request(Model $payable, PaymentMethod $method, int $amountCents, DateTimeInterface $expiresAt, Closure $send): PaymentAttempt
    {
        // A caller's rollback would erase the creating/unknown row that prevents a second payable reference.
        // Tests are exempt because RefreshDatabase wraps application tests in a transaction.
        if ((new PaymentAttempt)->getConnection()->transactionLevel() > 0 && ! app()->runningUnitTests()) {
            throw new LogicException('Eupago requests must not run inside a database transaction: a rollback would erase the attempt record and allow a duplicate payable reference.');
        }

        try {
            return Cache::lock("eupago:payable:{$payable->getMorphClass()}:{$payable->getKey()}", 30)
                ->block(10, fn () => $this->locked($payable, $method, $amountCents, $expiresAt, $send));
        } catch (LockTimeoutException) {
            throw new EupagoException('Another payment request for this payable is in progress.', FailureType::Blocked);
        }
    }

    /**
     * @param  Closure(PaymentAttempt): array{entity: string|null, reference: string, transaction_id: string|null}  $send
     */
    private function locked(Model $payable, PaymentMethod $method, int $amountCents, DateTimeInterface $expiresAt, Closure $send): PaymentAttempt
    {
        $unresolved = $this->attempts($payable)->whereIn('status', [AttemptStatus::Creating, AttemptStatus::Unknown])->latest('id')->first();

        if ($unresolved !== null) {
            throw new EupagoException('An earlier payment request for this payable has an unknown outcome.', FailureType::Blocked, $unresolved);
        }

        if ($method === PaymentMethod::Multibanco) {
            $pending = $this->attempts($payable)
                ->where('method', PaymentMethod::Multibanco)
                ->where('status', AttemptStatus::Pending)
                ->where('amount_cents', $amountCents)
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            if ($pending !== null) {
                return $pending;
            }
        }

        // Explicit refusals that never produced a reference (wrong key, mistyped phone) stop counting after
        // 15 minutes, so a configuration mistake does not use up the payable forever; a burst of refused
        // requests is still capped.
        $counted = $this->attempts($payable)->where(
            fn (Builder $query) => $query->where('status', '!=', AttemptStatus::Failed)
                ->orWhereNotNull('reference')
                ->orWhere('created_at', '>=', now()->subMinutes(15))
        );

        if ($counted->count() >= (int) config('eupago.max_attempts')) {
            throw new EupagoException('This payable reached the maximum number of payment attempts.', FailureType::Blocked);
        }

        $prefix = config('eupago.prefix');

        $attempt = PaymentAttempt::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'identifier' => (is_string($prefix) && $prefix !== '' ? $prefix.'-' : '').Str::ulid(),
            'method' => $method,
            'amount_cents' => $amountCents,
            'status' => AttemptStatus::Creating,
            'expires_at' => $expiresAt,
        ]);

        try {
            $result = $send($attempt);
        } catch (EupagoException $e) {
            $attempt->update([
                'status' => $e->type === FailureType::Rejected ? AttemptStatus::Failed : AttemptStatus::Unknown,
                'error_message' => $e->getMessage(),
            ]);

            throw new EupagoException($e->getMessage(), $e->type, $attempt);
        } catch (Throwable) {
            // Not chained and not echoed: the original could carry the request body or the API key.
            $message = 'The payment request failed unexpectedly; a payable reference may exist.';
            $attempt->update(['status' => AttemptStatus::Unknown, 'error_message' => $message]);

            throw new EupagoException($message, FailureType::Unknown, $attempt);
        }

        $attempt->update([
            'status' => AttemptStatus::Pending,
            'entity' => $result['entity'],
            'reference' => $result['reference'],
            'transaction_id' => $result['transaction_id'],
        ]);

        return $attempt;
    }

    /**
     * @return Builder<PaymentAttempt>
     */
    private function attempts(Model $payable): Builder
    {
        return PaymentAttempt::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey());
    }
}
