<?php

namespace Noop\Eupago\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Noop\Eupago\Filament\Resources\PaymentAttempts\PaymentAttemptResource;
use Noop\Eupago\Filament\Resources\PaymentWebhookEvents\PaymentWebhookEventResource;

/**
 * Adds the EuPago payment attempts and webhook events to a panel: `->plugins([EupagoPlugin::make()])`.
 * Access follows PaymentAttemptPolicy and PaymentWebhookEventPolicy (registered by the package).
 */
class EupagoPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'eupago';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([PaymentAttemptResource::class, PaymentWebhookEventResource::class]);
    }

    public function boot(Panel $panel): void {}
}
