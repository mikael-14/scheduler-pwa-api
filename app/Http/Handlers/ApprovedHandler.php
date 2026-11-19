<?php

namespace App\Http\Handlers;

use Rupadana\ApiService\Http\Handlers;

/**
 * Base for every generated API handler: besides the permission check, the caller
 * must be an active, approved account.
 */
class ApprovedHandler extends Handlers
{
    public static function getRouteMiddleware(): array
    {
        $middleware = parent::getRouteMiddleware();

        if (static::isPublic()) {
            return $middleware;
        }

        // auth:sanctum first, then account state, then the permission middleware
        array_splice($middleware, 1, 0, ['active', 'approved']);

        // Changes (bookings, joins, approvals...) email and push other people: a tighter limit
        if (strtoupper((string) static::getMethod()) !== 'GET') {
            $middleware[] = 'throttle:writes';
        }

        return $middleware;
    }
}
