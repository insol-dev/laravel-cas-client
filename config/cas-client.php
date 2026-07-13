<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CAS Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your CAS server connection settings.
    | The server_url should point to your One System installation.
    |
    */

    'server_url' => env('CAS_SERVER_URL', 'http://127.0.0.1:8001'),

    /*
    |--------------------------------------------------------------------------
    | Public (browser-facing) CAS Base URL
    |--------------------------------------------------------------------------
    |
    | Optional. PUBLIC base used ONLY to build the /sso/login redirect that the
    | end user's BROWSER follows. In split-horizon deployments the browser must
    | reach CAS at a public host while the app server reaches it at an internal
    | host (server_url, used for back-channel token validation). Leave empty to
    | fall back to server_url for single-url setups.
    |
    */

    'public_url' => env('CAS_PUBLIC_URL'),

    /*
    |--------------------------------------------------------------------------
    | Client System Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are registered in the CAS server for this client.
    | Uses client_id + client_secret for secure authentication.
    |
    */

    'client_id' => env('CAS_CLIENT_ID'),
    'client_secret' => env('CAS_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Callback Configuration
    |--------------------------------------------------------------------------
    |
    | URL where users will be redirected after CAS authentication.
    | Ideally this should be yourAPP_URL/cas/callback
    |
    */

    'callback_url' => env('CAS_CALLBACK_URL', env('APP_URL') . '/cas/callback'),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure signature validation and SSL verification.
    |
    */

    'enable_signature_validation' => env('CAS_ENABLE_SIGNATURE_VALIDATION', true),
    'signature_secret' => env('CAS_SIGNATURE_SECRET', 'default-signature-secret'),
    'verify_ssl' => env('CAS_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Timeout for requests to the CAS server.
    |
    */

    'timeout' => env('CAS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CAS client routes and redirection targets.
    |
    */

    'routes' => [
        'enabled' => env('CAS_ROUTES_ENABLED', true),
        'prefix' => env('CAS_ROUTES_PREFIX', 'cas'),
        'middleware' => ['web'],

        // Where to redirect after successful login
        'user_dashboard' => env('CAS_USER_DASHBOARD', '/dashboard'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    |
    | Configure how CAS users are handled in your application.
    |
    */

    'user' => [
        // Automatically create/update a local User record upon successful CAS login
        'create_local_users' => env('CAS_CREATE_LOCAL_USERS', true),

        // The Eloquent User model used for optional local login/provisioning.
        'model' => env('CAS_USER_MODEL', 'App\Models\Auth\User'),

        'defaults' => [
            'user_type' => 'Guest',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for CAS user data to reduce calls to the server.
    |
    */

    'cache' => [
        'enabled' => env('CAS_CACHE_ENABLED', true),
        'ttl' => env('CAS_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'cas_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for CAS operations.
    |
    */

    'logging' => [
        'enabled' => env('CAS_LOGGING_ENABLED', true),
        'channel' => env('CAS_LOG_CHANNEL', 'single'),
        'level' => env('CAS_LOG_LEVEL', 'info'),
    ],
];
