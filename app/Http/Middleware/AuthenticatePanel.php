<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Notifications\Notification;

/**
 * Filament's panel authentication, plus: a user deactivated while signed in is signed out and
 * sent to the login page with the reason, instead of being left on a bare 403 page (what
 * canAccessPanel() alone does) with no way to sign out.
 */
class AuthenticatePanel extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user && ! $user->status) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Notification::make()
                ->title(__('Your account is inactive.'))
                ->danger()
                ->persistent()
                ->send();

            $this->unauthenticated($request, $guards);
        }

        parent::authenticate($request, $guards);
    }
}
