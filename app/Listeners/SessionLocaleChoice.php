<?php

namespace App\Listeners;

use App\Http\Middleware\ApplyUserLocale;
use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

/**
 * The language picked with the admin panel's switcher holds only for this browser session: the
 * profile language (notifications, email, the next login) is changed on the profile page. Same
 * rule as the PWA's header switch.
 */
class SessionLocaleChoice
{
    /** Switched while signed in: keep it for this session (the switcher already put it there). */
    public function handleLocaleChanged(LocaleChanged $event): void
    {
        if (Auth::check() && request()->hasSession()) {
            request()->session()->put(ApplyUserLocale::SESSION_CHOICE, true);
        }
    }

    /** A new login starts in the profile language. */
    public function handleLogin(Login $event): void
    {
        if (request()->hasSession()) {
            request()->session()->forget(ApplyUserLocale::SESSION_CHOICE);
        }
    }
}
