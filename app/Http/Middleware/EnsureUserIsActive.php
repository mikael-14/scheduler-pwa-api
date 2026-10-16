<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks deactivated accounts (users.status = false) on the API.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->status) {
            return response()->json([
                'message' => __('Your account is inactive.'),
                'code' => 'account_inactive',
            ], 403);
        }

        return $next($request);
    }
}
