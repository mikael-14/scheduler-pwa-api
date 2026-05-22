<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;


class SchedulePendingWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {

          return [
            Stat::make('Pending Schedules', (string) \App\Models\Schedule::where('status', ScheduleStatus::Pending)->count())
                ->description('Schedules awaiting approval')
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info'),
        ];
    }
}

