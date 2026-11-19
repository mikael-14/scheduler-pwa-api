<?php

/*
 * Which web pages may call the API from a browser. Only the PWA (PWA_URL); in local development
 * also its Vite dev server. Requests carry a Bearer token, never cookies (supports_credentials off).
 * Add more origins, comma separated, in CORS_ALLOWED_ORIGINS.
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_unique(array_map(
        fn($origin) => rtrim(trim($origin), '/'),
        [
            env('PWA_URL', 'http://localhost:3000'),
            ...explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
            ...(env('APP_ENV') === 'local' ? ['http://localhost:5173', 'https://localhost:5173'] : []),
        ]
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
