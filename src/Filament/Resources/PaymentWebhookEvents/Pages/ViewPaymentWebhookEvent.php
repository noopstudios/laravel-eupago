<?php

namespace Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Pages;

use Filament\Resources\Pages\ViewRecord;
use Noop\Eupago\Filament\Actions\ReprocessWebhookEventAction;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;

class ViewPaymentWebhookEvent extends ViewRecord
{
    protected static string $resource = PaymentWebhookEventResource::class;

    protected function getHeaderActions(): array
    {
        return [ReprocessWebhookEventAction::make()];
    }
}
