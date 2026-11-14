<?php

namespace Noop\Eupago;

use Illuminate\Support\ServiceProvider;

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

        $this->publishes([
            __DIR__.'/../config/eupago.php' => config_path('eupago.php'),
        ], 'eupago-config');
    }
}
