<?php

use Illuminate\Support\Facades\DB;
use Noop\Eupago\Models\PaymentWebhookEvent;

it('stores the webhook payload encrypted', function () {
    $event = PaymentWebhookEvent::factory()->create(['payload' => ['transaction' => ['status' => 'Paid']]]);

    expect($event->fresh()?->payload)->toBe(['transaction' => ['status' => 'Paid']])
        ->and(DB::table('payment_webhook_events')->value('payload'))->not->toContain('Paid');
});
