<?php

namespace Noop\Eupago\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * The attempt was paid and the application's handler received the payment.
 */
class PaymentConfirmed implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly PaymentAttempt $attempt) {}
}
