<?php

namespace Noop\Eupago\Filament\Resources\PaymentAttempts\Pages;

use Filament\Resources\Pages\ViewRecord;
use Noop\Eupago\Filament\Actions\AbandonAttemptAction;
use Noop\Eupago\Filament\Resources\PaymentAttempts\PaymentAttemptResource;

class ViewPaymentAttempt extends ViewRecord
{
    protected static string $resource = PaymentAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [AbandonAttemptAction::make()];
    }
}
