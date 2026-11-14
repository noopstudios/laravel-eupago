<?php

namespace Noop\Eupago\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentAttempt;
use Throwable;

/**
 * Closes the pending and unknown attempts past their expiry, one transaction per attempt. A
 * payment that EuPago confirms later still marks the attempt paid.
 */
class ExpireCommand extends Command
{
    private const OPEN = [AttemptStatus::Pending, AttemptStatus::Unknown];

    protected $signature = 'eupago:expire';

    protected $description = 'Close the EuPago payment attempts past their expiry';

    public function handle(): int
    {
        $count = 0;
        $failed = 0;

        PaymentAttempt::query()
            ->whereIn('status', self::OPEN)
            ->where('expires_at', '<', now())
            ->lazyById()
            ->each(function (PaymentAttempt $attempt) use (&$count, &$failed) {
                try {
                    $count += DB::transaction(function () use ($attempt) {
                        $attempt = PaymentAttempt::query()->lockForUpdate()->find($attempt->id);

                        if ($attempt === null || ! in_array($attempt->status, self::OPEN, true) || ! $attempt->expires_at->isPast()) {
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
