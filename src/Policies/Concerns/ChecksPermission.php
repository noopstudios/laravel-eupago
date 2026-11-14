<?php

namespace Noop\Eupago\Policies\Concerns;

use Illuminate\Contracts\Auth\Access\Authorizable;

trait ChecksPermission
{
    /**
     * Whether the user has the ability named in `eupago.permissions.{$key}`. A null or empty
     * permission never grants.
     */
    protected static function permits(mixed $user, string $key): bool
    {
        $permission = config("eupago.permissions.{$key}");

        return $user instanceof Authorizable
            && is_string($permission)
            && $permission !== ''
            && $user->can($permission);
    }
}
