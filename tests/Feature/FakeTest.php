<?php

use Illuminate\Support\Facades\Http;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Facades\Eupago;
use Noop\Eupago\Tests\Fixtures\Order;

beforeEach(function () {
    handler();
    Http::preventStrayRequests();
    config(['eupago.api_key' => null]);
});

it('requests payments without any HTTP', function () {
    Eupago::fake();
    $attempt = Eupago::multibanco(Order::create(), 1250);

    expect($attempt->status)->toBe(AttemptStatus::Pending)
        ->and($attempt->reference)->toMatch('/^\d{9}$/')
        ->and($attempt->entity)->not->toBeEmpty();
    Http::assertNothingSent();
});

it('pays an attempt through the webhook path', function () {
    $fake = Eupago::fake();
    $attempt = Eupago::multibanco(Order::create(), 1250);

    $fake->pay($attempt);

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->received)->toHaveCount(1)
        ->and(handler()->received[0][0])->toBe($attempt->id);
});

it('expires an attempt through the webhook path', function () {
    $fake = Eupago::fake();
    $attempt = Eupago::multibanco(Order::create(), 1250);

    $fake->expire($attempt);

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Expired)
        ->and(handler()->expired)->toBe([$attempt->id]);
});
