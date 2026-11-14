<?php

namespace Noop\Eupago\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Noop\Eupago\Policies\Concerns\ChecksPermission;

/**
 * Webhook events are only read in the panel; the one change allowed is processing one again
 * (reprocess).
 */
class PaymentWebhookEventPolicy
{
    use ChecksPermission;

    public function viewAny(Authenticatable $user): bool
    {
        return self::permits($user, 'view_events');
    }

    public function view(Authenticatable $user, PaymentWebhookEvent $event): bool
    {
        return self::permits($user, 'view_events');
    }

    public function reprocess(Authenticatable $user, PaymentWebhookEvent $event): bool
    {
        return self::permits($user, 'reprocess_events');
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, PaymentWebhookEvent $event): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, PaymentWebhookEvent $event): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
