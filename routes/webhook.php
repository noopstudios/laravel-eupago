<?php

use Illuminate\Support\Facades\Route;
use Noop\Eupago\Http\WebhookController;

Route::post((string) config('eupago.webhook_path'), WebhookController::class)
    ->middleware('throttle:120,1')
    ->name('eupago.webhook');
