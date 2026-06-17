<?php

namespace App\Providers;

use App\Policies\AuditPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tapp\FilamentAuditing\Filament\Resources\Audits\AuditResource;
use Tapp\FilamentAuditing\Models\Audit;
use Illuminate\Notifications\DatabaseNotification;
use App\Observers\FilamentNotificationObserver;

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
        DatabaseNotification::observe(FilamentNotificationObserver::class);
        AuditResource::navigationGroup('Filament Shield'); // Change 'Settings' to your desired group name
        Gate::policy(Audit::class, AuditPolicy::class);
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
