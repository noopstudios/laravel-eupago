<?php

namespace Noop\Eupago\Data;

use Carbon\CarbonImmutable;
use Noop\Eupago\Enums\PaymentMethod;

/**
 * A payment EuPago confirmed for an attempt (Webhooks 2.0, status PAID).
 */
class ConfirmedPayment
{
    public function __construct(
        public readonly int $amountCents,
        public readonly string $trid,
        public readonly PaymentMethod $method,
        public readonly string $reference,
        public readonly ?string $entity,
        public readonly CarbonImmutable $paidAt,
    ) {}
}
