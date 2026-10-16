<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;


class UserPendingCounterWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('approve_user');
    }

    protected function getStats(): array
    {
        return [
            Stat::make(__('Pending Users'), (string) \App\Models\User::where('approved_at', null)->count())
                ->description(__('Users awaiting approval'))
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('warning'),
        ];
    }
}
