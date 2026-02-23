<?php

use App\Models\User;
use Tapp\FilamentAuthenticationLog\Resources\AuthenticationLogResource;

return [
    //'user-resource' => \App\Filament\Resources\UserResource::class,
    'resources' => [
        'AutenticationLogResource' => AuthenticationLogResource::class,
    ],

    'authenticable-resources' => [
        User::class,
    ],

    'authenticatable' => [
        'field-to-display' => 'name',
    ],

    'navigation' => [
        'authentication-log' => [
            'register' => false,
            'sort' => 1,
            'icon' => 'heroicon-o-shield-check',
            'group' => 'Filament Shield',
        ],
    ],

    'sort' => [
        'column' => 'login_at',
        'direction' => 'desc',
    ],
];
