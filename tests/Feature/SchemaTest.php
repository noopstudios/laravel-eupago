<?php

use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Noop\Eupago\Tests\Fixtures\Order;

it('stores attempts for a payable and events with an encrypted payload', function () {
    $order = Order::create();
    $attempt = PaymentAttempt::factory()->for($order, 'payable')->create();
    $event = PaymentWebhookEvent::factory()->create([
        'payment_attempt_id' => $attempt->id,
        'payload' => ['transaction' => ['status' => 'Paid']],
    ]);

    $attempt->refresh();
    expect($attempt->method)->toBe(PaymentMethod::Multibanco)
        ->and($attempt->status)->toBe(AttemptStatus::Pending)
        ->and($attempt->amount_cents)->toBe(1250)
        ->and($attempt->payable?->getKey())->toBe($order->id)
        ->and($attempt->webhookEvents)->toHaveCount(1);

    $event->refresh();
    expect($event->status)->toBe(EventStatus::Pending)
        ->and($event->payload)->toBe(['transaction' => ['status' => 'Paid']])
        ->and($event->attempt?->id)->toBe($attempt->id)
        ->and(DB::table('payment_webhook_events')->value('payload'))->not->toContain('Paid');
});
