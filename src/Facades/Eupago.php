<?php

namespace Noop\Eupago\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * @method static PaymentAttempt multibanco(Model $payable, int $amountCents)
 * @method static PaymentAttempt mbway(Model $payable, int $amountCents, string $phone)
 *
 * @see \Noop\Eupago\Eupago
 */
class Eupago extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Noop\Eupago\Eupago::class;
    }
}
