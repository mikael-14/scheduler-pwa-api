<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * People expected per day, split by approved vs. waiting for approval.
 * This is the "scale" view: how many people are at the shelter on each day.
 */
class ScheduleHeadcountChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '14';

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('view_any_schedule');
    }

    public function getHeading(): string | Htmlable | null
    {
        return __('People per day');
    }

    public function getDescription(): string | Htmlable | null
    {
        return __('Approved and pending people expected on each day.');
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => __('Next 7 days'),
            '14' => __('Next 14 days'),
            '30' => __('Next 30 days'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    protected function getData(): array
    {
        $days = max(1, (int) $this->filter);
        $first = CarbonImmutable::today();
        $last = $first->addDays($days - 1)->endOfDay();

        $schedules = Schedule::query()
            ->with('schedule_users')
            ->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled, ScheduleStatus::NotShown])
            ->where('start', '<=', $last)
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$first])
            ->get();

        $labels = [];
        $approved = [];
        $pending = [];

        for ($i = 0; $i < $days; $i++) {
            $dayStart = $first->addDays($i)->startOfDay();
            $dayEnd = $dayStart->endOfDay();

            $labels[] = $dayStart->translatedFormat('D d/m');

            $approvedPeople = [];
            $pendingPeople = [];

            foreach ($schedules as $schedule) {
                if ($schedule->start > $dayEnd || ($schedule->end ?? $schedule->start) < $dayStart) {
                    continue;
                }

                $people = collect([$schedule->user_id])
                    ->merge(
                        $schedule->schedule_users
                            ->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled])
                            ->pluck('user_id')
                    )
                    ->filter();

                if ($schedule->status === ScheduleStatus::Pending) {
                    $pendingPeople = array_merge($pendingPeople, $people->all());
                } else {
                    $approvedPeople = array_merge($approvedPeople, $people->all());
                }
            }

            $approved[] = count(array_unique($approvedPeople));
            $pending[] = count(array_diff(array_unique($pendingPeople), $approvedPeople));
        }

        return [
            'datasets' => [
                [
                    'label' => __('Approved'),
                    'data' => $approved,
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => __('Pending'),
                    'data' => $pending,
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
