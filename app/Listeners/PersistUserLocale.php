<?php

namespace App\Listeners;

use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;

/**
 * The language picked in the admin panel becomes the user's profile language, which is what the
 * PWA and the API read on the next login.
 */
class PersistUserLocale
{
    public function handle(LocaleChanged $event): void
    {
        $available = collect(config('app-locales.available'))->pluck('code')->all();

        if (! Auth::check() || ! in_array($event->newLocale, $available, true)) {
            return;
        }

        Auth::user()->update([
            'locale' => $event->newLocale,
        ]);
    }
}
