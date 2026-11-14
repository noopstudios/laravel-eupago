<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentWebhookEvent;

beforeEach(fn () => handler());

it('expires only the open attempts past their expiry and grace, without a pending webhook event, once', function () {
    $pending = attempt(['expires_at' => now()->subMinutes(3)]);
    $unknown = attempt(['status' => AttemptStatus::Unknown, 'expires_at' => now()->subMinutes(3)]);
    $inGrace = attempt(['expires_at' => now()->subMinute()]);
    $awaiting = attempt(['expires_at' => now()->subMinutes(3)]);
    PaymentWebhookEvent::factory()->create(['identifier' => $awaiting->identifier]);
    $current = attempt(['expires_at' => now()->addMinute()]);
    $paid = attempt(['status' => AttemptStatus::Paid, 'expires_at' => now()->subMinutes(3)]);

    expect(Artisan::call('eupago:expire'))->toBe(0)
        ->and(Artisan::call('eupago:expire'))->toBe(0);

    expect($pending->refresh()->status)->toBe(AttemptStatus::Expired)
        ->and($unknown->refresh()->status)->toBe(AttemptStatus::Expired)
        ->and($inGrace->refresh()->status)->toBe(AttemptStatus::Pending)
        ->and($awaiting->refresh()->status)->toBe(AttemptStatus::Pending)
        ->and($current->refresh()->status)->toBe(AttemptStatus::Pending)
        ->and($paid->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->expired)->toBe([$pending->id, $unknown->id]);
});

it('marks stale creating attempts unknown and dispatches stale pending events again', function () {
    Queue::fake();
    $stale = attempt(['status' => AttemptStatus::Creating, 'created_at' => now()->subMinutes(3)]);
    $recent = attempt(['status' => AttemptStatus::Creating]);
    $lost = PaymentWebhookEvent::factory()->create(['created_at' => now()->subHour(), 'updated_at' => now()->subMinutes(16)]);
    // Old, but its job failed recently and is waiting for its backoff.
    PaymentWebhookEvent::factory()->create(['created_at' => now()->subHour(), 'updated_at' => now()->subMinutes(14)]);
    PaymentWebhookEvent::factory()->status(EventStatus::Processed)->create(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);

    expect(Artisan::call('eupago:recover'))->toBe(0);

    expect($stale->refresh()->status)->toBe(AttemptStatus::Unknown)
        ->and($recent->refresh()->status)->toBe(AttemptStatus::Creating);
    Queue::assertPushedTimes(ProcessWebhookEvent::class, 1);
    Queue::assertPushed(ProcessWebhookEvent::class, fn (ProcessWebhookEvent $job) => $job->event->is($lost));
});
