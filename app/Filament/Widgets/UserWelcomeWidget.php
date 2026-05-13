<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Facades\Filament;


class UserWelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.user-welcome-widget';

    // Make the widget span the full width of the dashboard
    protected int | string | array $columnSpan = 'full';

    // Optional: Set the sort order so it appears at the very top
    protected static ?int $sort = -5;

    public static function canView(): bool
    {
        return Filament::auth()->check();
    }
}
