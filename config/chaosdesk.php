<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    |
    | The ingest token is a server-to-server credential. It must never be
    | exposed to a browser or shipped inside a mobile application: clients
    | authenticate against *your* application, which forwards to ChaosDesk.
    |
    */

    'enabled' => (bool) env('CHAOSDESK_ENABLED', true),

    'url' => rtrim((string) env('CHAOSDESK_URL', 'https://chaosdesk.eu/api/v1'), '/'),

    'site_token' => env('CHAOSDESK_SITE_TOKEN'),

    'timeout' => (int) env('CHAOSDESK_TIMEOUT', 10),

    'retries' => (int) env('CHAOSDESK_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Context
    |--------------------------------------------------------------------------
    |
    | Which groups of diagnostic context to collect automatically. Turn any of
    | them off if you would rather not send that information.
    |
    */

    'context' => [
        'app' => true,
        'runtime' => true,
        'page' => true,
        'user' => true,
        'console' => true,

        // Reported as app.version. Falls back to the APP_VERSION env var.
        'app_version' => env('APP_VERSION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Registered by ChaosDesk::routes(). Wrap that call in your own auth
    | middleware; these endpoints are what your mobile apps talk to.
    |
    */

    'routes' => [
        'prefix' => env('CHAOSDESK_ROUTE_PREFIX', 'chaosdesk'),
        'name' => 'chaosdesk.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire components
    |--------------------------------------------------------------------------
    */

    'components' => [
        'support' => 'chaosdesk-support',
        'tickets' => 'chaosdesk-tickets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'enabled' => true,
        'max_kilobytes' => 10240,
        'accepted' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
    ],

];
