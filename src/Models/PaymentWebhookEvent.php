<?php

namespace Noop\Eupago\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Noop\Eupago\Database\Factories\PaymentWebhookEventFactory;
use Noop\Eupago\Enums\EventStatus;

/**
 * A verified webhook delivery, stored before it is processed. `event_key` (the hash of the raw
 * body) makes a repeated delivery a no-op.
 *
 * @property int $id
 * @property int|null $payment_attempt_id
 * @property string $event_key
 * @property string|null $identifier
 * @property array<string, mixed> $payload
 * @property EventStatus $status
 * @property string|null $error_message
 * @property Carbon|null $processed_at
 */
class PaymentWebhookEvent extends Model
{
    /** @use HasFactory<PaymentWebhookEventFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): PaymentWebhookEventFactory
    {
        return PaymentWebhookEventFactory::new();
    }

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'status' => EventStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }
}
