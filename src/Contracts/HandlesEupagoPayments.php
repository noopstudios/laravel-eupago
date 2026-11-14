<?php

namespace Noop\Eupago\Contracts;

use Noop\Eupago\Data\ConfirmedPayment;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * Implemented by the application and bound in the container. received() and expired() run inside
 * the database transaction that changes the attempt: throwing rolls the change back and the
 * webhook event is retried.
 */
interface HandlesEupagoPayments
{
    public function canReceive(PaymentAttempt $attempt): bool;

    public function received(PaymentAttempt $attempt, ConfirmedPayment $payment): void;

    public function expired(PaymentAttempt $attempt): void;
}
