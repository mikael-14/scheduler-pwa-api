<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ScheduleCalendar;
use App\Services\AgendaSummary;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * This week at a glance, Monday to Sunday: schedules and people on each day (approved and
 * pending), and for the primary type how many approved people there are, warning on the days
 * still to come that have nobody. A day opens the calendar on it.
 */
class WeekOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.week-overview';

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('view_any_schedule');
    }

    protected function getViewData(): array
    {
        $today = CarbonImmutable::today();
        $primary = AgendaSummary::primaryType();
        $primaryDays = $primary ? AgendaSummary::week($today, $primary->id) : [];

        $days = collect(AgendaSummary::week($today))->map(fn(array $day, int $i) => $day + [
            'primary' => $primaryDays[$i]['approved'] ?? null,
            'isToday' => $day['date']->isSameDay($today),
            'isPast' => $day['date']->lt($today),
            'url' => ScheduleCalendar::getUrl(['date' => $day['date']->toDateString()]),
        ]);

        return [
            'days' => $days,
            'max' => max(1, $days->max(fn(array $day) => $day['approved'] + $day['pending'])),
            'primary' => $primary,
            'range' => $today->startOfWeek()->translatedFormat('d M') . ' – ' . $today->endOfWeek()->translatedFormat('d M'),
        ];
    }
}
