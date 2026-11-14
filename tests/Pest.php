<?php

use Noop\Eupago\Contracts\HandlesEupagoPayments;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Tests\Fixtures\Order;
use Noop\Eupago\Tests\Fixtures\RecordingHandler;
use Noop\Eupago\Tests\TestCase;
use Noop\Eupago\Tests\WithoutFilamentTestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
pest()->extend(WithoutFilamentTestCase::class)->in('WithoutFilament');
// Real calls to the EuPago sandbox: run only with --group=sandbox (see tests/Integration/SandboxTest.php).
pest()->extend(TestCase::class)->group('sandbox')->in('Integration');

/**
 * The application's payment handler, bound on first use in each test.
 */
function handler(): RecordingHandler
{
    if (! app()->bound(HandlesEupagoPayments::class)) {
        app()->instance(HandlesEupagoPayments::class, new RecordingHandler);
    }

    $handler = app(HandlesEupagoPayments::class);
    assert($handler instanceof RecordingHandler);

    return $handler;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function attempt(array $attributes = []): PaymentAttempt
{
    return PaymentAttempt::factory()->for(Order::create(), 'payable')->create($attributes);
}
