<?php

namespace Noop\Eupago\Filament\Resources\PaymentAttempts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Filament\Resources\PaymentAttempts\Pages\ListPaymentAttempts;
use Noop\Eupago\Filament\Resources\PaymentAttempts\Pages\ViewPaymentAttempt;
use Noop\Eupago\Filament\Resources\PaymentAttempts\Schemas\PaymentAttemptInfolist;
use Noop\Eupago\Filament\Resources\PaymentAttempts\Tables\PaymentAttemptsTable;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * The EuPago payment attempts, read-only: they change through payments, webhooks and the
 * "mark as failed" action. Access follows PaymentAttemptPolicy.
 */
class PaymentAttemptResource extends Resource
{
    protected static ?string $model = PaymentAttempt::class;

    protected static ?string $slug = 'eupago-payment-attempts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'identifier';

    public static function getModelLabel(): string
    {
        return __('EuPago payment attempt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('EuPago payment attempts');
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentAttemptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentAttemptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentAttempts::route('/'),
            'view' => ViewPaymentAttempt::route('/{record}'),
        ];
    }

    public static function methodLabel(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Multibanco => 'Multibanco',
            PaymentMethod::Mbway => 'MB WAY',
        };
    }

    public static function statusLabel(AttemptStatus $status): string
    {
        return match ($status) {
            AttemptStatus::Creating => __('Creating'),
            AttemptStatus::Pending => __('Awaiting payment'),
            AttemptStatus::Paid => __('Paid'),
            AttemptStatus::Expired => __('Expired'),
            AttemptStatus::Failed => __('Failed'),
            AttemptStatus::Unknown => __('Unknown'),
        };
    }

    public static function statusColor(AttemptStatus $status): string
    {
        return match ($status) {
            AttemptStatus::Creating, AttemptStatus::Expired => 'gray',
            AttemptStatus::Pending => 'info',
            AttemptStatus::Paid => 'success',
            AttemptStatus::Failed => 'danger',
            AttemptStatus::Unknown => 'warning',
        };
    }
}
