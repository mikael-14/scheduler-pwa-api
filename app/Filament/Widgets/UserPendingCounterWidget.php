<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;


class UserPendingCounterWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending Users', (string) \App\Models\User::where('approved_at', null)->count())
                ->description('Users awaiting approval')
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('warning'),
        ];
    }
}
