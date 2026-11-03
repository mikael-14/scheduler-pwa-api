<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardCounters;
use App\Filament\Widgets\PendingSchedulesWidget;
use App\Filament\Widgets\ScheduleHeadcountChart;
use App\Filament\Widgets\TodaySchedulesWidget;
use App\Filament\Widgets\WeekOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The most important first, with little scrolling: the counters (what waits for someone, how busy
 * the week and today are), then two columns: pending approvals beside the week, and the
 * people-per-day chart beside today. Each widget shows only to who may see it.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            DashboardCounters::class,
            PendingSchedulesWidget::make(['compact' => true]),
            WeekOverviewWidget::class,
            ScheduleHeadcountChart::class,
            TodaySchedulesWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
