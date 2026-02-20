<?php

namespace App\Listeners;

use CraftForge\FilamentLanguageSwitcher\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;

class PersistUserLocale
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LocaleChanged $event): void
    {
        if (!Auth::check()) {
            return;
        }

        Auth::user()->update([
            'locale' => $event->newLocale,
        ]);
    }
}
