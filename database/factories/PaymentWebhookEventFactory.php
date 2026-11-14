<?php

namespace Noop\Eupago\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Models\PaymentWebhookEvent;

/**
 * @extends Factory<PaymentWebhookEvent>
 */
class PaymentWebhookEventFactory extends Factory
{
    protected $model = PaymentWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => hash('sha256', Str::random(40)),
            'identifier' => 'TEST-'.Str::ulid(),
            'payload' => ['transaction' => ['status' => 'Paid']],
            'status' => EventStatus::Pending,
        ];
    }

    public function status(EventStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
