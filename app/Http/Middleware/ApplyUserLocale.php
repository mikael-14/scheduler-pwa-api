<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a signed-in user is the `users.locale` column; it is the one source of truth
 * for both the admin panel and the API. Guests keep the default (pt) or whatever they picked
 * on the login page.
 *
 * Runs on every panel request, including Livewire updates, and after the language switcher's
 * own SetLocale middleware (which only knows about session/cookie).
 */
class ApplyUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $available = collect(config('app-locales.available'))->pluck('code')->all();

        if ($user && in_array($user->locale, $available, true)) {
            App::setLocale($user->locale);

            // Keep the switcher's own state in sync so its menu shows the right language
            if ($request->hasSession()) {
                $request->session()->put('locale', $user->locale);
            }
        }

        return $next($request);
    }
}
