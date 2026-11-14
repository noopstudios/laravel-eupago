<?php

namespace Noop\Eupago\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * The attempt was paid, but the application's handler could not receive it (canReceive() returned false): the money must be reviewed by hand.
 */
class PaymentNeedsReview implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly PaymentAttempt $attempt) {}
}
