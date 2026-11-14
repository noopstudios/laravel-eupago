<?php

namespace Noop\Eupago\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Testing\EupagoFake;

/**
 * @method static PaymentAttempt multibanco(Model $payable, int $amountCents)
 * @method static PaymentAttempt mbway(Model $payable, int $amountCents, string $phone)
 *
 * @see \Noop\Eupago\Eupago
 */
class Eupago extends Facade
{
    /**
     * Swaps the EuPago client for a fake that makes no HTTP calls; the fake plays the webhook.
     */
    public static function fake(): EupagoFake
    {
        app()->forgetInstance(\Noop\Eupago\Eupago::class);
        static::clearResolvedInstance(\Noop\Eupago\Eupago::class);

        return new EupagoFake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Noop\Eupago\Eupago::class;
    }
}
