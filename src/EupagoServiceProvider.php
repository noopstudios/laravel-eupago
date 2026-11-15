<?php

namespace Noop\Eupago;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Noop\Eupago\Console\ExpireCommand;
use Noop\Eupago\Console\RecoverCommand;
use Noop\Eupago\Models\PaymentAttempt;
use Noop\Eupago\Models\PaymentWebhookEvent;
use Noop\Eupago\Policies\PaymentAttemptPolicy;
use Noop\Eupago\Policies\PaymentWebhookEventPolicy;

class EupagoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eupago.php', 'eupago');

        $this->app->singleton(EupagoClient::class);
        $this->app->singleton(Eupago::class);

        // In register(): a host that translates before this provider boots would cache the JSON lines without ours.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');

        // Explicit, so the models stay guarded when the host guesses policy names its own way;
        // a policy the host registered for a model is kept.
        foreach ([PaymentAttempt::class => PaymentAttemptPolicy::class, PaymentWebhookEvent::class => PaymentWebhookEventPolicy::class] as $model => $policy) {
            if (! array_key_exists($model, Gate::policies())) {
                Gate::policy($model, $policy);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireCommand::class, RecoverCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('eupago:expire')->everyMinute()->withoutOverlapping(10);
            $schedule->command('eupago:recover')->everyFiveMinutes()->withoutOverlapping(10);
        });

        $this->publishes([
            __DIR__.'/../config/eupago.php' => config_path('eupago.php'),
        ], 'eupago-config');
    }
}
