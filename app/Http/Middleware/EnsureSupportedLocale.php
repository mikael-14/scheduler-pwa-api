<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the language switcher route (registered on the web group, acts only on that route): its {code} segment is stored in the session, in a cookie
 * and, for signed-in users, in users.locale, so it must be one of the configured languages.
 */
class EnsureSupportedLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() === 'filament-language-switcher.switch') {
            $available = collect(config('app-locales.available'))->pluck('code')->all();

            abort_unless(in_array($request->route('code'), $available, true), 404);
        }

        return $next($request);
    }
}
