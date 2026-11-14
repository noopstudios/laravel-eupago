<?php

namespace Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;
use Noop\Eupago\Models\PaymentWebhookEvent;

class PaymentWebhookEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('EuPago webhook event'))
                ->columns(2)
                ->schema([
                    TextEntry::make('identifier')->label(__('Identifier'))->placeholder('-')->copyable(),
                    TextEntry::make('status')->label(__('Status'))->badge()
                        ->formatStateUsing(fn (EventStatus $state): string => PaymentWebhookEventResource::statusLabel($state))
                        ->color(fn (EventStatus $state): string => PaymentWebhookEventResource::statusColor($state)),
                    TextEntry::make('transaction_status')->label(__('EuPago status'))->placeholder('-')
                        ->state(fn (PaymentWebhookEvent $record): ?string => PaymentWebhookEventResource::transactionStatus($record)),
                    TextEntry::make('attempt.identifier')->label(__('EuPago payment attempt'))->placeholder('-'),
                    TextEntry::make('created_at')->label(__('Received at'))->dateTime(),
                    TextEntry::make('processed_at')->label(__('Processed at'))->dateTime()->placeholder('-'),
                    TextEntry::make('error_message')->label(__('Error'))->placeholder('-')->columnSpanFull(),
                ]),
            Section::make(__('Payload'))
                ->schema([
                    // The decrypted body as EuPago sent it, escaped and pretty-printed.
                    TextEntry::make('payload')->hiddenLabel()
                        ->state(fn (PaymentWebhookEvent $record): HtmlString => new HtmlString('<pre style="white-space: pre-wrap; font-family: monospace;">'
                            .e((string) json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                            .'</pre>')),
                ]),
        ]);
    }
}
