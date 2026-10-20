<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a signed-in user is the `users.locale` column; it is the one source of truth
 * for both the admin panel and the API. Guests keep the default (pt) or whatever they picked
 * on the login page.
 *
 * Runs on every panel request, including Livewire updates, and after the language switcher's
 * own SetLocale middleware (which only knows about session/cookie). It costs no query: the
 * user is already loaded by the session guard.
 */
class ApplyUserLocale
{
    /** The language switcher's "remember my language" cookie (see rememberLocale() in the panel). */
    public const SWITCHER_COOKIE = 'filament_language_switcher_locale';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $available = collect(config('app-locales.available'))->pluck('code')->all();

        if ($user && in_array($user->locale, $available, true)) {
            App::setLocale($user->locale);

            // Keep the switcher's own state in sync so its menu shows the right language
            if ($request->hasSession() && $request->session()->get('locale') !== $user->locale) {
                $request->session()->put('locale', $user->locale);
            }

            // ...and its cookie, so after logout the login page stays in the user's language
            // (e.g. after changing it in the profile or in the PWA)
            if ($request->cookie(self::SWITCHER_COOKIE) !== $user->locale) {
                Cookie::queue(self::SWITCHER_COOKIE, $user->locale, self::COOKIE_MINUTES);
            }
        }

        return $next($request);
    }
}
