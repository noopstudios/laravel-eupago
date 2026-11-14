<?php

use Filament\FilamentServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\LivewireServiceProvider;
use Noop\Eupago\Actions\AbandonAttempt;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Facades\Eupago;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Policies\PaymentAttemptPolicy;
use Noop\Eupago\Tests\Fixtures\Order;

it('boots, takes a payment and abandons an attempt with no Filament provider registered', function () {
    expect(app()->getProviders(FilamentServiceProvider::class))->toBe([])
        ->and(app()->getProviders(LivewireServiceProvider::class))->toBe([]);

    Http::preventStrayRequests();
    handler();
    $fake = Eupago::fake();
    $attempt = Eupago::multibanco(Order::create(), 1250);
    $fake->pay($attempt);

    $unknown = attempt(['status' => AttemptStatus::Unknown]);

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Paid)
        ->and(handler()->received)->toHaveCount(1)
        ->and(AbandonAttempt::run($unknown))->toBeTrue()
        ->and($unknown->refresh()->status)->toBe(AttemptStatus::Failed)
        ->and(Gate::getPolicyFor(PaymentAttempt::class))->toBeInstanceOf(PaymentAttemptPolicy::class);
});
