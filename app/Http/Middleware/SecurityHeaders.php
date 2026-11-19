<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections on every response of the API and the admin panel:
 * - nosniff: a file is only ever run as the type the server says (e.g. an uploaded "image")
 * - SAMEORIGIN: no other site can show the admin panel in a frame to trick clicks (clickjacking)
 * - Referrer-Policy: other sites only see the domain, never a full admin URL
 * - HSTS (production over HTTPS): the browser never tries plain HTTP again for a year
 *
 * No Content-Security-Policy here: Filament / Livewire / Alpine need `unsafe-eval`, which would
 * make it pointless. The PWA, which keeps its token in the browser, has a strict one (.htaccess).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
