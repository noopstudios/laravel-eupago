<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\EventStatus;
use Noop\Eupago\Events\PaymentAttemptExpired;
use Noop\Eupago\Events\PaymentConfirmed;
use Noop\Eupago\Events\PaymentNeedsReview;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;

beforeEach(fn () => handler());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payload(PaymentAttempt $attempt, array $overrides = []): array
{
    return ['transaction' => array_replace([
        'identifier' => $attempt->identifier,
        'status' => 'Paid',
        'amount' => ['value' => '12.50', 'currency' => 'EUR'],
        'reference' => $attempt->reference,
        'entity' => $attempt->entity,
        'trid' => '7654321',
        'date' => '2026-11-14 10:00:00',
    ], $overrides)];
}

function sign(string $body): string
{
    return base64_encode(hash_hmac('sha256', $body, 'fake-webhook-secret', true));
}

/**
 * @return TestResponse<Response>
 */
function deliverRaw(string $body, ?string $signature): TestResponse
{
    return call('POST', '/eupago/webhook', server: array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature]), content: $body);
}

/**
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function deliver(array $payload): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return deliverRaw($body, sign($body));
}

/**
 * @param  array<string, mixed>  $payload
 */
function storedEvent(PaymentAttempt $attempt, array $payload): PaymentWebhookEvent
{
    return PaymentWebhookEvent::factory()->create(['identifier' => $attempt->identifier, 'payload' => $payload]);
}

it('stores a correctly signed delivery', function () {
    $attempt = attempt();

    deliver(payload($attempt))->assertOk();

    expect(PaymentWebhookEvent::sole()->identifier)->toBe($attempt->identifier);
});

it('refuses a delivery without a valid signature and stores nothing', function (?string $signature) {
    deliverRaw(json_encode(payload(attempt()), JSON_THROW_ON_ERROR), $signature)->assertUnauthorized();

    expect(PaymentWebhookEvent::count())->toBe(0);
})->with([
    'wrong' => base64_encode(str_repeat('x', 32)),
    'missing' => null,
]);

it('verifies the signature over the raw bytes', function () {
    $payload = payload(attempt());

    deliverRaw(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), sign(json_encode($payload, JSON_THROW_ON_ERROR)))
        ->assertUnauthorized();

    expect(PaymentWebhookEvent::count())->toBe(0);
});

it('answers 503 without a webhook secret', function () {
    config(['eupago.webhook_secret' => null]);

    deliver(payload(attempt()))->assertStatus(503);

    expect(PaymentWebhookEvent::count())->toBe(0);
});

it('processes a repeated delivery once', function () {
    Event::fake([PaymentConfirmed::class]);
    $attempt = attempt();

    deliver(payload($attempt))->assertOk();
    deliver(payload($attempt))->assertOk();

    $attempt->refresh();
    expect(PaymentWebhookEvent::sole()->status)->toBe(EventStatus::Processed)
        ->and($attempt->status)->toBe(AttemptStatus::Paid)
        ->and($attempt->trid)->toBe('7654321')
        ->and(handler()->received)->toHaveCount(1)
        ->and(handler()->received[0][1]->amountCents)->toBe(1250);
    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
});

it('rejects a payment that does not match the attempt', function (array $overrides) {
    $attempt = attempt();

    deliver(payload($attempt, $overrides))->assertOk();

    expect(PaymentWebhookEvent::sole()->status)->toBe(EventStatus::Rejected)
        ->and($attempt->refresh()->status)->toBe(AttemptStatus::Pending)
        ->and(handler()->received)->toBe([]);
})->with([
    'amount' => [['amount' => ['value' => '12.51', 'currency' => 'EUR']]],
    'reference' => [['reference' => '999999999']],
    'entity' => [['entity' => '99999']],
]);

it('completes an unknown attempt with the reference from the payment', function () {
    $attempt = attempt(['status' => AttemptStatus::Unknown, 'reference' => null, 'entity' => null]);

    deliver(payload($attempt, ['reference' => '800000001', 'entity' => '12345']))->assertOk();

    $attempt->refresh();
    expect($attempt->status)->toBe(AttemptStatus::Paid)
        ->and($attempt->reference)->toBe('800000001')
        ->and($attempt->entity)->toBe('12345')
        ->and(handler()->received)->toHaveCount(1);
});

it('marks the attempt paid for review when the handler cannot receive it', function () {
    Event::fake([PaymentConfirmed::class, PaymentNeedsReview::class]);
    handler()->accepts = false;
    $attempt = attempt();

    deliver(payload($attempt))->assertOk();

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->received)->toBe([]);
    Event::assertDispatched(PaymentNeedsReview::class);
    Event::assertNotDispatched(PaymentConfirmed::class);
});

it('marks an expired attempt paid', function () {
    $attempt = attempt(['status' => AttemptStatus::Expired]);

    deliver(payload($attempt))->assertOk();

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->received)->toHaveCount(1);
});

it('orphans an event for an unknown identifier', function () {
    deliver(payload(attempt(), ['identifier' => 'TEST-UNKNOWN']))->assertOk();

    expect(PaymentWebhookEvent::sole()->status)->toBe(EventStatus::Orphaned);
});

it('waits while the attempt is still being created', function () {
    $attempt = attempt(['status' => AttemptStatus::Creating, 'reference' => null, 'entity' => null]);
    $event = storedEvent($attempt, payload($attempt, ['reference' => '800000001']));
    $job = (new ProcessWebhookEvent($event))->withFakeQueueInteractions();

    $job->handle();

    $job->assertReleased(30);
    expect($event->refresh()->status)->toBe(EventStatus::Pending)
        ->and($attempt->refresh()->status)->toBe(AttemptStatus::Creating);
});

it('rolls back and retries when the handler fails', function () {
    $attempt = attempt();
    $event = storedEvent($attempt, payload($attempt));
    handler()->throws = new RuntimeException('The order is locked.');

    expect(fn () => (new ProcessWebhookEvent($event))->handle())->toThrow(RuntimeException::class, 'The order is locked.')
        ->and($attempt->refresh()->status)->toBe(AttemptStatus::Pending)
        ->and($event->refresh()->status)->toBe(EventStatus::Pending)
        ->and($event->error_message)->toBe('The order is locked.');

    handler()->throws = null;
    (new ProcessWebhookEvent($event))->handle();

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and($event->refresh()->status)->toBe(EventStatus::Processed)
        ->and($event->error_message)->toBeNull()
        ->and(handler()->received)->toHaveCount(1);
});

it('closes a pending attempt that EuPago expires or cancels, and leaves a paid one alone', function (string $status) {
    Event::fake([PaymentAttemptExpired::class]);
    $pending = attempt();
    $paid = attempt(['status' => AttemptStatus::Paid, 'trid' => '1234', 'paid_at' => now()]);

    deliver(payload($pending, ['status' => $status]))->assertOk();
    deliver(payload($paid, ['status' => $status]))->assertOk();

    expect($pending->refresh()->status)->toBe(AttemptStatus::Expired)
        ->and($paid->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->expired)->toBe([$pending->id]);
    Event::assertDispatchedTimes(PaymentAttemptExpired::class, 1);
})->with(['Expired', 'Canceled']);
