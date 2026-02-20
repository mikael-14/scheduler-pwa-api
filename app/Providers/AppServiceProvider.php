<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
         Event::listen(LocaleChanged::class, function (LocaleChanged $event) {
            if (Auth::check()) {
                Auth::user()->update([
                    'locale' => $event->newLocale,
                ]);
            }
        });
    }
}
