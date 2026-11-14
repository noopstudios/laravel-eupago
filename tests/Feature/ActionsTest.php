<?php

use Illuminate\Support\Facades\Queue;
use Noop\Eupago\Actions\AbandonAttempt;
use Noop\Eupago\Actions\ReprocessWebhookEvent;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Models\PaymentWebhookEvent;

beforeEach(fn () => handler());

it('never reprocesses a processed event', function () {
    Queue::fake();
    $event = PaymentWebhookEvent::factory()->status(EventStatus::Processed)->create(['processed_at' => now()]);

    expect(ReprocessWebhookEvent::run($event))->toBeFalse()
        ->and($event->fresh()?->status)->toBe(EventStatus::Processed);
    Queue::assertNothingPushed();
});

it('abandons only an unknown attempt', function (AttemptStatus $status) {
    $attempt = attempt(['status' => $status]);

    expect(AbandonAttempt::run($attempt))->toBeFalse()
        ->and($attempt->fresh()?->status)->toBe($status)
        ->and($attempt->fresh()?->error_message)->toBeNull()
        ->and(handler()->expired)->toBe([]);
})->with([AttemptStatus::Pending, AttemptStatus::Paid]);
