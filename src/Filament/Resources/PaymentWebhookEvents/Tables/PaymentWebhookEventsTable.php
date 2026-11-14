<?php

namespace Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Filament\Actions\ReprocessWebhookEventAction;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;
use Noop\Eupago\Models\PaymentWebhookEvent;

class PaymentWebhookEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Received at'))->dateTime(),
                TextColumn::make('identifier')->label(__('Identifier'))->placeholder('-')->searchable(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (EventStatus $state): string => PaymentWebhookEventResource::statusLabel($state))
                    ->color(fn (EventStatus $state): string => PaymentWebhookEventResource::statusColor($state)),
                TextColumn::make('transaction_status')->label(__('EuPago status'))->placeholder('-')
                    ->state(fn (PaymentWebhookEvent $record): ?string => PaymentWebhookEventResource::transactionStatus($record)),
                TextColumn::make('error_message')->label(__('Error'))->placeholder('-')->limit(80)->wrap(),
                TextColumn::make('processed_at')->label(__('Processed at'))->dateTime()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(EventStatus::cases())->mapWithKeys(fn (EventStatus $status): array => [$status->value => PaymentWebhookEventResource::statusLabel($status)])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ReprocessWebhookEventAction::make(),
            ]);
    }
}
