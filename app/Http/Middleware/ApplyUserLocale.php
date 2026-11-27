<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a signed-in user is the `users.locale` column (also used for their
 * notifications and email), unless they switched language in this browser session with the
 * panel's switcher: that choice holds only for this session, until logout or the next login
 * (see App\Listeners\SessionLocaleChoice). Guests keep the default (pt) or whatever they
 * picked on the login page.
 *
 * Runs on every panel request, including Livewire updates, and after the language switcher's
 * own SetLocale middleware (which only knows about session/cookie). It costs no query: the
 * user is already loaded by the session guard.
 */
class ApplyUserLocale
{
    /** The language switcher's "remember my language" cookie (see rememberLocale() in the panel). */
    public const SWITCHER_COOKIE = 'filament_language_switcher_locale';

    /** Session flag: the language in the session was picked with the switcher while signed in. */
    public const SESSION_CHOICE = 'locale_chosen_in_session';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $available = collect(config('app-locales.available'))->pluck('code')->all();

        $sessionChoice = $request->hasSession() && $request->session()->get(self::SESSION_CHOICE)
            ? $request->session()->get('locale')
            : null;

        $locale = match (true) {
            ! $user => null,
            in_array($sessionChoice, $available, true) => $sessionChoice,
            in_array($user->locale, $available, true) => $user->locale,
            default => null,
        };

        if ($locale) {
            App::setLocale($locale);

            // Keep the switcher's own state in sync so its menu shows the right language
            if ($request->hasSession() && $request->session()->get('locale') !== $locale) {
                $request->session()->put('locale', $locale);
            }

            // ...and its cookie, so after logout the login page stays in the language last shown
            if ($request->cookie(self::SWITCHER_COOKIE) !== $locale) {
                Cookie::queue(self::SWITCHER_COOKIE, $locale, self::COOKIE_MINUTES);
            }
        }

        return $next($request);
    }
}
