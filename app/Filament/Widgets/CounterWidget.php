<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;


class CounterWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = [];
        if (Filament::auth()->user()->can('ViewAny:User')) {
            $stats[] = Stat::make('Pending Users', (string) \App\Models\User::where('approved_at', null)->count())
                ->description('Users awaiting approval')
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('warning');
        }
        if (Filament::auth()->user()->can('ViewAny:Schedule')) {
            $stats[] = Stat::make('Pending Schedules', (string) \App\Models\Schedule::where('status', ScheduleStatus::Pending)->count())
                ->description('Schedules awaiting approval')
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info');
        }
        return $stats;
    }
}
