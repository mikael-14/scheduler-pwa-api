<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class SchedulesByTypeChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('view_any_schedule');
    }

    public function getHeading(): string | Htmlable | null
    {
        return __('Schedules by type');
    }

    public function getDescription(): string | Htmlable | null
    {
        return __('Upcoming schedules in the next 30 days.');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Schedule::query()
            ->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled])
            ->whereBetween('start', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
            ->selectRaw('schedule_type_id, COUNT(*) as total')
            ->groupBy('schedule_type_id')
            ->pluck('total', 'schedule_type_id');

        $types = ScheduleType::whereIn('id', $counts->keys()->filter())->get()->keyBy('id');

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($counts as $typeId => $total) {
            $type = $types->get($typeId);

            $labels[] = $type?->name ?? __('No type');
            $data[] = $total;
            $colors[] = $type?->color ?? '#9ca3af';
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
