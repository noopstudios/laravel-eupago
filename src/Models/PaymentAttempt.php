<?php

namespace Noop\Eupago\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Noop\Eupago\Database\Factories\PaymentAttemptFactory;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\PaymentMethod;

/**
 * One request to EuPago for a payable: a Multibanco reference or an MB WAY request.
 *
 * @property int $id
 * @property string $payable_type
 * @property int|string $payable_id
 * @property string $identifier
 * @property PaymentMethod $method
 * @property int $amount_cents
 * @property string|null $entity
 * @property string|null $reference
 * @property string|null $transaction_id
 * @property string|null $trid
 * @property AttemptStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property string|null $error_message
 */
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): PaymentAttemptFactory
    {
        return PaymentAttemptFactory::new();
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => AttemptStatus::class,
            'amount_cents' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<PaymentWebhookEvent, $this>
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }
}
