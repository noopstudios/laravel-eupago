<?php

/*
 * The package against the real EuPago sandbox. Excluded from the default run; run it with:
 *
 *     EUPAGO_SANDBOX_ENV=/path/to/.env.sandbox vendor/bin/pest --group=sandbox
 *
 * The env file holds EUPAGO_API_KEY and, for the MB WAY test, EUPAGO_SANDBOX_PHONE (see
 * .env.sandbox.example). Without EUPAGO_SANDBOX_ENV every test is skipped. The sandbox is forced
 * on, so this suite never reaches production. Each run creates real sandbox references.
 */

use Dotenv\Dotenv;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Enums\PaymentMethod;
use Noop\Eupago\Facades\Eupago;
use Noop\Eupago\Tests\Fixtures\Order;
use PHPUnit\Framework\Assert;

/**
 * @return array<string, string>
 */
function sandboxValues(): array
{
    $path = getenv('EUPAGO_SANDBOX_ENV');

    if (! is_string($path) || $path === '' || ! is_file($path)) {
        Assert::markTestSkipped('Set EUPAGO_SANDBOX_ENV to an env file to run the EuPago sandbox suite.');
    }

    return array_map('trim', array_filter(Dotenv::parse((string) file_get_contents($path)), 'is_string'));
}

beforeEach(function () {
    $values = sandboxValues();

    if (($values['EUPAGO_API_KEY'] ?? '') === '') {
        Assert::fail('The sandbox env file has no EUPAGO_API_KEY.');
    }

    config([
        'eupago.api_key' => $values['EUPAGO_API_KEY'],
        'eupago.sandbox' => true,
        'eupago.prefix' => 'SBX',
    ]);
});

it('creates a real Multibanco reference', function () {
    $attempt = Eupago::multibanco(Order::create(), 1250);

    expect($attempt->status)->toBe(AttemptStatus::Pending)
        ->and($attempt->method)->toBe(PaymentMethod::Multibanco)
        ->and($attempt->reference)->not->toBeEmpty()
        ->and($attempt->entity)->not->toBeEmpty();
});

it('creates a real MB WAY request', function () {
    $phone = sandboxValues()['EUPAGO_SANDBOX_PHONE'] ?? getenv('EUPAGO_SANDBOX_PHONE');

    if (! is_string($phone) || $phone === '') {
        Assert::markTestSkipped('Set EUPAGO_SANDBOX_PHONE to run the MB WAY sandbox test.');
    }

    $attempt = Eupago::mbway(Order::create(), 1250, $phone);

    expect($attempt->status)->toBe(AttemptStatus::Pending)
        ->and($attempt->method)->toBe(PaymentMethod::Mbway)
        ->and($attempt->reference)->not->toBeEmpty();
});
