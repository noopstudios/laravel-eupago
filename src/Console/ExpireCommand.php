<?php

namespace Noop\Eupago\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Throwable;

/**
 * Closes the pending and unknown attempts past their expiry plus a grace, one transaction per
 * attempt. An attempt with a webhook event still pending (queued, or retrying) waits for it. A
 * payment that EuPago confirms later still marks the attempt paid.
 */
class ExpireCommand extends Command
{
    private const OPEN = [AttemptStatus::Pending, AttemptStatus::Unknown];

    private const GRACE_MINUTES = 2;

    protected $signature = 'eupago:expire';

    protected $description = 'Close the EuPago payment attempts past their expiry';

    public function handle(): int
    {
        $count = 0;
        $failed = 0;
        $cutoff = now()->subMinutes(self::GRACE_MINUTES);

        PaymentAttempt::query()
            ->whereIn('status', self::OPEN)
            ->where('expires_at', '<', $cutoff)
            ->lazyById()
            ->each(function (PaymentAttempt $attempt) use (&$count, &$failed, $cutoff) {
                try {
                    $count += DB::transaction(function () use ($attempt, $cutoff) {
                        $attempt = PaymentAttempt::query()->lockForUpdate()->find($attempt->id);

                        if ($attempt === null || ! in_array($attempt->status, self::OPEN, true) || ! $attempt->expires_at->lt($cutoff)) {
                            return 0;
                        }

                        // A payment made in time may be waiting in the queue or in its job's backoff.
                        if (PaymentWebhookEvent::query()->where('identifier', $attempt->identifier)->where('status', EventStatus::Pending)->exists()) {
                            return 0;
                        }

                        ProcessWebhookEvent::close($attempt, AttemptStatus::Expired);

                        return 1;
                    });
                } catch (Throwable $e) {
                    // One failing handler must not stop the other attempts.
                    report($e);
                    $failed++;
                }
            });

        $this->components->info("Expired {$count} EuPago payment attempts.");

        if ($failed > 0) {
            $this->components->warn("{$failed} EuPago payment attempts failed to expire.");
        }

        return self::SUCCESS;
    }
}
