<?php

namespace App\Providers;

use App\Policies\AuditPolicy;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        DatabaseNotification::observe(FilamentNotificationObserver::class);
        Gate::policy(Audit::class, AuditPolicy::class);
        // Saving the language chosen in the panel's switcher is done by the
        // App\Listeners\PersistUserLocale listener (auto-discovered); it used to be duplicated here.

        // Generated labels ("Schedule type id", "Internal note", ...) are looked up in lang/pt.json
        Field::configureUsing(fn(Field $component) => $component->translateLabel());
        Column::configureUsing(fn(Column $column) => $column->translateLabel());
        Entry::configureUsing(fn(Entry $entry) => $entry->translateLabel());

        // Date pickers follow the language of the signed-in user (they are English otherwise)
        Flatpickr::configureUsing(fn(Flatpickr $picker) => $picker->locale(fn() => app()->getLocale()));
        // Wait until all third-party packages are fully loaded...
        $this->app->booted(function () {
            // Overwrite the package's broken gate definitions
            Gate::define('restoreAudit', function ($user, $audit = null) {
                // 1. When Filament does its structural check, $audit is null.
                // We return true here so the button can safely render without crashing.
                if ($audit === null) {
                    return true; 
                }

                // 2. When a user actually clicks 'Restore' on a specific row, $audit is passed.
                // We forward the check directly to your Shield permission!
                return $user->can('restore_audit');
            });

        });
    }
}
