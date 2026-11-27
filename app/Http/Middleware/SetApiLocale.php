<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * API language, first supported value wins:
 *  1. the X-App-Language header: the language the PWA shows on that device, which a user can
 *     switch there without changing their profile;
 *  2. the authenticated user's `locale` (other clients send the browser's Accept-Language,
 *     which must not override the profile);
 *  3. the Accept-Language header (so login and register errors match the PWA language);
 *  4. the app default (pt).
 *
 * Notifications and mail are not affected: they use the recipient's preferredLocale().
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = collect(config('app-locales.available'))->pluck('code')->all();

        $locale = null;

        $appLanguage = $request->header('X-App-Language');
        if (is_string($appLanguage) && in_array($appLanguage, $available, true)) {
            $locale = $appLanguage;
        }

        // Token auth is resolved lazily here, ahead of the route's own auth middleware
        $user = ! $locale && $request->bearerToken() ? $request->user('sanctum') : null;
        if ($user && in_array($user->locale, $available, true)) {
            $locale = $user->locale;
        }

        if (! $locale && $request->headers->has('Accept-Language')) {
            $locale = $request->getPreferredLanguage($available);
        }

        if ($locale && in_array($locale, $available, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
