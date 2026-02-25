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
        if ($user && $user->approved_at === null) {
            // Don't redirect if already on the pending approval page or trying to logout
            if ($request->routeIs('filament.admin.pages.pending-approval') || $request->routeIs('filament.admin.auth.logout')) {
                return $next($request);
            }

            // Redirect to pending approval page
            return redirect()->route('filament.admin.pages.pending-approval');
        } elseif ($user && $user->approved_at !== null) {
            // If the user is approved but has a pending status, redirect to the dashboard
            if ($request->routeIs('filament.admin.pages.pending-approval')) {
                return redirect()->route('filament.admin.pages.dashboard');
            }
        }

        return $next($request);
    }
}
