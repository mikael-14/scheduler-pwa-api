<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends signed-in accounts an admin has not approved yet (users.approved_at is null) to the
 * pending approval page. Runs after AuthenticatePanel, so the user is signed in and active here.
 * Registered as persistent so Livewire requests from an already open tab are checked too.
 */
class CheckPendingApproval
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        // The email comes first: Filament's own middleware sends an unconfirmed account to its
        // "verify your email" screen, which must not be redirected here (that would loop)
        if ($user && ! $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($user && $user->approved_at === null
            && ! $request->routeIs('filament.admin.pages.pending-approval', 'filament.admin.auth.logout', 'filament.admin.auth.email-verification.*', 'filament.admin.auth.email-change-verification.*')) {
            return redirect()->route('filament.admin.pages.pending-approval');
        }

        return $next($request);
    }
}
