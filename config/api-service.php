<?php

return [
    'navigation' => [
        'token' => [
            'cluster' => null,
            'group' => 'Administration',
            'sort' => -1,
            'icon' => 'heroicon-o-key',
            'should_register_navigation' => true,
        ],
    ],
    'models' => [
        'token' => [
            'enable_policy' => true,
        ],
    ],
    'route' => [
        'panel_prefix' => true,
        'use_resource_middlewares' => false,
    ],
    'tenancy' => [
        'enabled' => false,
        'awareness' => false,
    ],
    'login-rules' => [
        'email' => 'required|email',
        'password' => 'required',
    ],
    'login-middleware' => [
        // The app's own POST /api/auth/login (routes/api.php) replaces the plugin's; should the
        // plugin's ever answer, it is limited the same way
        'throttle:login',
    ],
    'logout-middleware' => [
        'auth:sanctum',
        // Add any additional middleware you want to apply to the logout route
    ],
    'use-spatie-permission-middleware' => true,
];
