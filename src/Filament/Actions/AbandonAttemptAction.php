<?php

namespace Noop\Eupago\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Noop\Eupago\Actions\AbandonAttempt;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * Marks an unknown payment attempt as failed (AbandonAttempt).
 */
class AbandonAttemptAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'abandon';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Mark as failed'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('abandon')
            ->visible(fn (?PaymentAttempt $record): bool => $record?->status === AttemptStatus::Unknown)
            ->requiresConfirmation()
            ->modalDescription(__('First confirm in the EuPago backoffice that no reference or MB WAY request exists for this identifier. If one exists and is paid later, the webhook will still mark this attempt as paid.'))
            ->action(function (PaymentAttempt $record): void {
                if (! AbandonAttempt::run($record)) {
                    Notification::make()->danger()->title(__('The attempt is no longer unknown.'))->send();

                    return;
                }

                Notification::make()->success()->title(__('The attempt was marked as failed.'))->send();
            });
    }
}
