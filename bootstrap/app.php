<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The language switcher's route accepts any {code}; this keeps it to the supported languages
        $middleware->web(append: [
            \App\Http\Middleware\EnsureSupportedLocale::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SetApiLocale::class,
        ]);

        // Every API route counts towards the `api` limit (AppServiceProvider::configureRateLimits)
        $middleware->throttleApi('api');

        // Every response, the admin panel's included (its routes do not use the web group)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Signed-out: the API answers 401 (no redirect, see withExceptions); anything else goes to
        // the admin panel's login (there is no route named `login`)
        $middleware->redirectGuestsTo(
            fn(\Illuminate\Http\Request $request) => $request->is('api/*') ? null : route('filament.admin.auth.login')
        );

        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'approved' => \App\Http\Middleware\EnsureUserIsApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API only speaks JSON, whatever the client's Accept header: a signed-out request
        // gets a 401 instead of a redirect to a `login` route that does not exist (a 500, and a
        // stack trace in the log for every such request)
        $exceptions->shouldRenderJsonWhen(
            fn(\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
