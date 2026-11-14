<?php

namespace Noop\Eupago\Tests\Fixtures;

use Noop\Eupago\Contracts\HandlesEupagoPayments;
use Noop\Eupago\Data\ConfirmedPayment;
use Noop\Eupago\Models\PaymentAttempt;
use Throwable;

class RecordingHandler implements HandlesEupagoPayments
{
    public bool $accepts = true;

    public ?Throwable $throws = null;

    /** @var list<array{int, ConfirmedPayment}> */
    public array $received = [];

    /** @var list<int> */
    public array $expired = [];

    public function canReceive(PaymentAttempt $attempt): bool
    {
        return $this->accepts;
    }

    public function received(PaymentAttempt $attempt, ConfirmedPayment $payment): void
    {
        if ($this->throws !== null) {
            throw $this->throws;
        }

        $this->received[] = [$attempt->id, $payment];
    }

    public function expired(PaymentAttempt $attempt): void
    {
        $this->expired[] = $attempt->id;
    }
}
