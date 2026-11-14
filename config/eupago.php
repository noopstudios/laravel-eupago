<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EuPago credentials
    |--------------------------------------------------------------------------
    |
    | Only ever read from the environment. They are never stored in the
    | database, written to logs or included in exceptions. The webhook secret
    | is the "cryptographic key" of the channel's Webhooks 2.0.
    |
    */

    'api_key' => env('EUPAGO_API_KEY'),

    'webhook_secret' => env('EUPAGO_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox
    |--------------------------------------------------------------------------
    |
    | When true, requests go to sandbox.eupago.pt instead of clientes.eupago.pt.
    |
    */

    'sandbox' => (bool) env('EUPAGO_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    |
    | Prepended to every payment identifier ("PREFIX-ULID"), so several apps
    | can share one EuPago channel.
    |
    */

    'prefix' => env('EUPAGO_PREFIX'),

    /*
    |--------------------------------------------------------------------------
    | Payment rules
    |--------------------------------------------------------------------------
    |
    | multibanco_days: days a Multibanco reference stays valid (until the end
    | of that day, Europe/Lisbon). mbway_minutes: minutes the customer has to
    | approve an MB WAY request. max_attempts: payment attempts per payable.
    |
    */

    'multibanco_days' => 3,

    'mbway_minutes' => 5,

    'max_attempts' => 5,

    /*
    |--------------------------------------------------------------------------
    | Webhook path
    |--------------------------------------------------------------------------
    |
    | The path EuPago's Webhooks 2.0 posts to, relative to the app's URL.
    |
    */

    'webhook_path' => 'eupago/webhook',

    /*
    |--------------------------------------------------------------------------
    | Filament permissions
    |--------------------------------------------------------------------------
    |
    | Abilities checked with $user->can() for the EuPago panel resources and
    | actions. A null permission grants nothing.
    |
    */

    'permissions' => [
        'view_attempts' => 'view eupago attempts',
        'view_events' => 'view eupago events',
        'reprocess_events' => 'reprocess eupago events',
        'abandon_attempts' => 'abandon eupago attempts',
    ],

];
