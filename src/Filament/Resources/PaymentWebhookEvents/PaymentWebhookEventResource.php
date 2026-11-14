<?php

namespace Noop\Eupago\Filament\Resources\PaymentWebhookEvents;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Pages\ListPaymentWebhookEvents;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Pages\ViewPaymentWebhookEvent;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Schemas\PaymentWebhookEventInfolist;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\Tables\PaymentWebhookEventsTable;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentWebhookEvent;

/**
 * The EuPago webhook events, read-only: they change only through processing and the
 * "reprocess" action. Access follows PaymentWebhookEventPolicy.
 */
class PaymentWebhookEventResource extends Resource
{
    protected static ?string $model = PaymentWebhookEvent::class;

    protected static ?string $slug = 'eupago-webhook-events';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $recordTitleAttribute = 'identifier';

    public static function getModelLabel(): string
    {
        return __('EuPago webhook event');
    }

    public static function getPluralModelLabel(): string
    {
        return __('EuPago webhook events');
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentWebhookEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentWebhookEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentWebhookEvents::route('/'),
            'view' => ViewPaymentWebhookEvent::route('/{record}'),
        ];
    }

    /**
     * The transaction status EuPago sent ("Paid", "Expired"...), if the payload has one.
     */
    public static function transactionStatus(PaymentWebhookEvent $event): ?string
    {
        $status = ProcessWebhookEvent::transaction($event->payload)['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public static function statusLabel(EventStatus $status): string
    {
        return match ($status) {
            EventStatus::Pending => __('Pending'),
            EventStatus::Processed => __('Processed'),
            EventStatus::Rejected => __('Rejected'),
            EventStatus::Orphaned => __('Orphaned'),
        };
    }

    public static function statusColor(EventStatus $status): string
    {
        return match ($status) {
            EventStatus::Pending => 'info',
            EventStatus::Processed => 'success',
            EventStatus::Rejected => 'danger',
            EventStatus::Orphaned => 'warning',
        };
    }
}
