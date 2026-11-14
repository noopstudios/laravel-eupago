<?php

namespace Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Pages;

use Filament\Resources\Pages\ListRecords;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;

class ListPaymentWebhookEvents extends ListRecords
{
    protected static string $resource = PaymentWebhookEventResource::class;
}
