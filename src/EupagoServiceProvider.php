<?php

namespace Noop\Eupago;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Noop\Eupago\Console\ExpireCommand;
use Noop\Eupago\Console\RecoverCommand;

class EupagoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eupago.php', 'eupago');

        $this->app->singleton(EupagoClient::class);
        $this->app->singleton(Eupago::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');

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
