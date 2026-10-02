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

    // Single-site shorthand for the agent client: the team API token of the
    // "default" site. Named sites carry their own agent_token below.
    'agent_token' => env('CHAOSDESK_AGENT_TOKEN'),

    'timeout' => (int) env('CHAOSDESK_TIMEOUT', 10),

    'retries' => (int) env('CHAOSDESK_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Sites
    |--------------------------------------------------------------------------
    |
    | One application can front several ChaosDesk sites, for example one per
    | audience ("customers", "gurus", ...). Each named site carries its own
    | ingest token, an optional agent token (a team API token, used by the
    | agent client) and the numeric site id ChaosDesk assigned to it.
    |
    | The "default" site is what ChaosDesk::class talks to unless you ask for
    | another one with forSite(). Leaving its token or agent_token empty falls
    | back to the single-site "site_token" and "agent_token" above, so
    | existing installs keep working.
    |
    */

    'sites' => [
        'default' => [
            'token' => env('CHAOSDESK_SITE_TOKEN'),
            'agent_token' => env('CHAOSDESK_AGENT_TOKEN'),
            'site_id' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | Whether the package registers its own migrations. Turn this off when you
    | bind a custom TicketStore and do not want the chaosdesk_tickets table.
    | Publishing the migrations stays possible either way.
    |
    */

    'migrations' => (bool) env('CHAOSDESK_MIGRATIONS', true),

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
        'community_board' => 'chaosdesk-community-board',
        'community_thread' => 'chaosdesk-community-thread',
        'community_new_thread' => 'chaosdesk-community-new-thread',
        'community_polls' => 'chaosdesk-community-polls',
        'community_charter' => 'chaosdesk-community-charter',
    ],

    /*
    |--------------------------------------------------------------------------
    | Community
    |--------------------------------------------------------------------------
    |
    | Your application decides where the community lives and who may see it.
    | Name the routes the components link to: each route receives only the
    | parameters it declares out of `board` (the slug), `thread` (the ulid)
    | and `site`. A page left null shows inline inside the board component.
    | For anything a route name cannot express, register a callback with
    | ThreeOhEight\ChaosDesk\Community\CommunityUrls::resolveUsing().
    |
    */

    'community' => [
        'routes' => [
            'board' => null,
            'thread' => null,
            'new_thread' => null,
            'polls' => null,
        ],

        // Threads per page on the board, at most 50.
        'per_page' => 20,
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
