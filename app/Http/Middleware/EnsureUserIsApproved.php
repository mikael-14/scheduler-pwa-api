<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks accounts an admin has not approved yet (users.approved_at is null).
 * The PWA still needs GET /api/user and logout while pending, so those routes skip this.
 */
class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->approved_at === null) {
            return response()->json([
                'message' => __('Your account is awaiting approval.'),
                'code' => 'account_pending',
            ], 403);
        }

        return $next($request);
    }
}
