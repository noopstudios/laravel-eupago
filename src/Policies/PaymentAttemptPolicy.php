<?php

namespace Noop\Eupago\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Policies\Concerns\ChecksPermission;

/**
 * Payment attempts are only read in the panel; the one change allowed is marking an unknown
 * attempt as failed (abandon).
 */
class PaymentAttemptPolicy
{
    use ChecksPermission;

    public function viewAny(Authenticatable $user): bool
    {
        return self::permits($user, 'view_attempts');
    }

    public function view(Authenticatable $user, PaymentAttempt $attempt): bool
    {
        return self::permits($user, 'view_attempts');
    }

    public function abandon(Authenticatable $user, PaymentAttempt $attempt): bool
    {
        return self::permits($user, 'abandon_attempts');
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, PaymentAttempt $attempt): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, PaymentAttempt $attempt): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
