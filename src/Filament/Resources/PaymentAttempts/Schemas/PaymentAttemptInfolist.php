<?php

namespace Noop\Eupago\Filament\Resources\PaymentAttempts\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Filament\Resources\PaymentAttempts\PaymentAttemptResource;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;

class PaymentAttemptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('EuPago payment attempt'))
                ->columns(2)
                ->schema([
                    TextEntry::make('identifier')->label(__('Identifier'))->copyable(),
                    TextEntry::make('method')->label(__('Method'))
                        ->formatStateUsing(fn (PaymentMethod $state): string => PaymentAttemptResource::methodLabel($state)),
                    TextEntry::make('amount_cents')->label(__('Amount'))->money('EUR', divideBy: 100),
                    TextEntry::make('status')->label(__('Status'))->badge()
                        ->formatStateUsing(fn (AttemptStatus $state): string => PaymentAttemptResource::statusLabel($state))
                        ->color(fn (AttemptStatus $state): string => PaymentAttemptResource::statusColor($state)),
                    TextEntry::make('entity')->label(__('Entity'))->placeholder('-'),
                    TextEntry::make('reference')->label(__('Reference'))->placeholder('-'),
                    TextEntry::make('transaction_id')->label(__('MB WAY transaction'))->placeholder('-'),
                    TextEntry::make('trid')->label(__('Paid transaction'))->placeholder('-'),
                    TextEntry::make('payable_type')->label(__('Payable type')),
                    TextEntry::make('payable_id')->label(__('Payable id')),
                    TextEntry::make('expires_at')->label(__('Expires at'))->dateTime(),
                    TextEntry::make('paid_at')->label(__('Paid at'))->dateTime()->placeholder('-'),
                    TextEntry::make('created_at')->label(__('Created at'))->dateTime(),
                    TextEntry::make('updated_at')->label(__('Updated at'))->dateTime(),
                    TextEntry::make('error_message')->label(__('Error'))->placeholder('-')->columnSpanFull(),
                ]),
            Section::make(__('Webhook events'))
                ->schema([
                    RepeatableEntry::make('webhookEvents')
                        ->hiddenLabel()
                        ->placeholder(__('No webhook events.'))
                        ->columns(4)
                        ->schema([
                            TextEntry::make('created_at')->label(__('Received at'))->dateTime(),
                            TextEntry::make('status')->label(__('Status'))->badge()
                                ->formatStateUsing(fn (EventStatus $state): string => PaymentWebhookEventResource::statusLabel($state))
                                ->color(fn (EventStatus $state): string => PaymentWebhookEventResource::statusColor($state)),
                            TextEntry::make('error_message')->label(__('Error'))->placeholder('-'),
                            TextEntry::make('processed_at')->label(__('Processed at'))->dateTime()->placeholder('-'),
                        ]),
                ]),
        ]);
    }
}
