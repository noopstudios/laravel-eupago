<?php

namespace Noop\Eupago\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * The attempt closed without a payment: expired, cancelled or failed (see its status).
 */
class PaymentAttemptExpired implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly PaymentAttempt $attempt) {}
}
