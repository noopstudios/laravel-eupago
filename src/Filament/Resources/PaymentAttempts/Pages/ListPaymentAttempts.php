<?php

namespace Noop\Eupago\Filament\Resources\PaymentAttempts\Pages;

use Filament\Resources\Pages\ListRecords;
use Noop\Eupago\Filament\Resources\PaymentAttempts\PaymentAttemptResource;

class ListPaymentAttempts extends ListRecords
{
    protected static string $resource = PaymentAttemptResource::class;
}
