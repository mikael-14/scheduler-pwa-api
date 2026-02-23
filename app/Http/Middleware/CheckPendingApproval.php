<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPendingApproval
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // If user is authenticated and status is null (pending approval)
        if ($user && $user->status === null) {
            // Don't redirect if already on the pending approval page or trying to logout
            if ($request->routeIs('filament.admin.pages.pending-approval') || $request->routeIs('filament.admin.auth.logout')) {
                return $next($request);
            }

            // Redirect to pending approval page
            return redirect()->route('filament.admin.pages.pending-approval');
        }

        return $next($request);
    }
}
