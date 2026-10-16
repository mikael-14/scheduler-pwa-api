<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;


class SchedulePendingWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user?->can('approve_schedule') || $user?->can('update_status_schedule'));
    }

    protected function getStats(): array
    {

          return [
            Stat::make(__('Pending Schedules'), (string) \App\Models\Schedule::where('status', ScheduleStatus::Pending)->count())
                ->description(__('Schedules awaiting approval'))
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info'),
        ];
    }
}

