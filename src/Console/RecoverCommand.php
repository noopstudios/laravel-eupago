<?php

namespace Noop\Eupago\Console;

use Illuminate\Console\Command;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Throwable;

/**
 * Marks unknown the attempts left creating (the request died before its response was saved: a
 * reference may exist), and dispatches again the webhook events whose job was lost.
 */
class RecoverCommand extends Command
{
    private const CREATING_MINUTES = 2;

    private const PENDING_MINUTES = 15;

    protected $signature = 'eupago:recover';

    protected $description = 'Resolve interrupted EuPago payment requests and dispatch lost webhook events again';

    public function handle(): int
    {
        $unknown = PaymentAttempt::query()
            ->where('status', AttemptStatus::Creating)
            ->where('created_at', '<', now()->subMinutes(self::CREATING_MINUTES))
            ->update(['status' => AttemptStatus::Unknown, 'error_message' => 'The payment request was interrupted; its outcome is unknown.']);

        $count = 0;
        $failed = 0;

        PaymentWebhookEvent::query()
            ->where('status', EventStatus::Pending)
            // Last touched, so an event that keeps failing is not queued again on top of its job's backoff.
            ->where('updated_at', '<', now()->subMinutes(self::PENDING_MINUTES))
            ->lazyById()
            ->each(function (PaymentWebhookEvent $event) use (&$count, &$failed) {
                try {
                    ProcessWebhookEvent::dispatch($event);
                    $count++;
                } catch (Throwable $e) {
                    // On a sync connection the job runs here; one event must not stop the others.
                    report($e);
                    $failed++;
                }
            });

        $this->components->info("Marked {$unknown} EuPago payment attempts unknown and dispatched {$count} webhook events again.");

        if ($failed > 0) {
            $this->components->warn("{$failed} EuPago webhook events failed while being dispatched again.");
        }

        return self::SUCCESS;
    }
}
