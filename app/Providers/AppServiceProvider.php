<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentAuditing\Filament\Resources\Audits\AuditResource;

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
        //use Tapp\FilamentAuditing\Resources\AuditResource;

        // This forces the AuditResource to live in a specific group
        AuditResource::navigationGroup('Filament Shield'); // Change 'Settings' to your desired group name
        //AuditResource::navigationIcon('heroicon-o-clipboard-document-check');
        Event::listen(LocaleChanged::class, function (LocaleChanged $event) {
            if (Auth::check()) {
                Auth::user()->update([
                    'locale' => $event->newLocale,
                ]);
            }
        });
    }
}
