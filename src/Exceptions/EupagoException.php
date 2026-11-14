<?php

namespace Noop\Eupago\Exceptions;

use Noop\Eupago\Enums\FailureType;
use Noop\Eupago\Models\PaymentAttempt;
use RuntimeException;

class EupagoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly FailureType $type,
        public readonly ?PaymentAttempt $attempt = null,
    ) {
        parent::__construct($message);
    }
}
