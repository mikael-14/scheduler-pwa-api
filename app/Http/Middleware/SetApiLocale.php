<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * API language: the authenticated user's `locale`, otherwise the Accept-Language header
 * (so login and register errors match the PWA language), otherwise the app default (pt).
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = collect(config('app-locales.available'))->pluck('code')->all();

        $locale = null;

        // Token auth is resolved lazily here, ahead of the route's own auth middleware
        $user = $request->bearerToken() ? $request->user('sanctum') : null;
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
