<?php

namespace Noop\Eupago\Actions;

use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentWebhookEvent;

/**
 * Processes an orphaned, rejected or pending webhook event again: back to pending, then
 * ProcessWebhookEvent. A processed event is never reprocessed.
 */
class ReprocessWebhookEvent
{
    public const STATUSES = [EventStatus::Orphaned, EventStatus::Rejected, EventStatus::Pending];

    /**
     * False when the event is no longer in one of STATUSES.
     */
    public static function run(PaymentWebhookEvent $event): bool
    {
        $reset = PaymentWebhookEvent::query()
            ->whereKey($event->id)
            ->whereIn('status', self::STATUSES)
            ->update(['status' => EventStatus::Pending, 'error_message' => null, 'processed_at' => null]);

        if ($reset === 0) {
            return false;
        }

        ProcessWebhookEvent::dispatch($event)->afterCommit();

        return true;
    }
}
