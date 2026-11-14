<?php

namespace Noop\Eupago\Actions;

use Illuminate\Support\Facades\DB;
use Noop\Eupago\Enums\AttemptStatus;
use Noop\Eupago\Jobs\ProcessWebhookEvent;
use Noop\Eupago\Models\PaymentAttempt;

/**
 * Marks an unknown attempt (the answer to its request was lost) as failed, so the payable can
 * request a payment again. Only after confirming in the EuPago backoffice that no reference or
 * request exists for its identifier: if one does and is paid later, the webhook still marks the
 * attempt paid. Closes it like any other attempt that ends without a payment: the handler's
 * expired() and PaymentAttemptExpired.
 */
class AbandonAttempt
{
    /**
     * False when the attempt is no longer unknown.
     */
    public static function run(PaymentAttempt $attempt): bool
    {
        return DB::transaction(function () use ($attempt): bool {
            $locked = PaymentAttempt::query()->lockForUpdate()->find($attempt->id);

            if ($locked?->status !== AttemptStatus::Unknown) {
                return false;
            }

            $locked->error_message = 'Abandoned manually after confirming in the EuPago backoffice that no reference or request exists.';
            ProcessWebhookEvent::close($locked, AttemptStatus::Failed);

            return true;
        });
    }
}
