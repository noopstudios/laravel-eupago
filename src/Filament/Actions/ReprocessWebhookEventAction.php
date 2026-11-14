<?php

namespace Noop\Eupago\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Noop\Eupago\Actions\ReprocessWebhookEvent;
use Noop\Eupago\Models\PaymentWebhookEvent;

/**
 * Processes an orphaned, rejected or pending webhook event again (ReprocessWebhookEvent).
 */
class ReprocessWebhookEventAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reprocess';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The job may run on a queue: it must see the event already reset, so never inside a
        // panel's transaction.
        $this->databaseTransaction(false);

        $this->label(__('Reprocess'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->authorize('reprocess')
            ->visible(fn (?PaymentWebhookEvent $record): bool => in_array($record?->status, ReprocessWebhookEvent::STATUSES, true))
            ->requiresConfirmation()
            ->modalDescription(__('The event is set back to pending and processed again against its payment attempt.'))
            ->action(function (PaymentWebhookEvent $record): void {
                if (! ReprocessWebhookEvent::run($record)) {
                    Notification::make()->danger()->title(__('The event can no longer be reprocessed.'))->send();

                    return;
                }

                Notification::make()->success()->title(__('The event was sent to be processed again.'))->send();
            });
    }
}
