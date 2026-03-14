<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cross-origin resource sharing. Allows the Providus API
    | to accept requests from the GitHub Pages frontend and localhost dev.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_merge(
        [
            'https://michelevens.github.io',
        ],
        // Add env-based origins (comma-separated) for custom agency domains
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
    )),

    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
