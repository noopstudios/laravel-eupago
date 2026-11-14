<?php

namespace Noop\Eupago\Filament\Resources\PaymentAttempts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Filament\Actions\AbandonAttemptAction;
use Noop\Eupago\Filament\Resources\PaymentAttempts\PaymentAttemptResource;

class PaymentAttemptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('identifier')->label(__('Identifier'))->searchable(),
                TextColumn::make('method')->label(__('Method'))
                    ->formatStateUsing(fn (PaymentMethod $state): string => PaymentAttemptResource::methodLabel($state)),
                TextColumn::make('amount_cents')->label(__('Amount'))->money('EUR', divideBy: 100),
                TextColumn::make('entity')->label(__('Entity'))->placeholder('-'),
                TextColumn::make('reference')->label(__('Reference'))->placeholder('-')->searchable(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (AttemptStatus $state): string => PaymentAttemptResource::statusLabel($state))
                    ->color(fn (AttemptStatus $state): string => PaymentAttemptResource::statusColor($state)),
                TextColumn::make('expires_at')->label(__('Expires at'))->dateTime(),
                TextColumn::make('paid_at')->label(__('Paid at'))->dateTime()->placeholder('-'),
                TextColumn::make('created_at')->label(__('Created at'))->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(AttemptStatus::cases())->mapWithKeys(fn (AttemptStatus $status): array => [$status->value => PaymentAttemptResource::statusLabel($status)])->all()),
                SelectFilter::make('method')->label(__('Method'))
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => PaymentAttemptResource::methodLabel($method)])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                AbandonAttemptAction::make(),
            ]);
    }
}
