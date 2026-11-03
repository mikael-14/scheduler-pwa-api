<?php

namespace App\Filament\Widgets;

use App\Models\ScheduleType;
use App\Services\AgendaSummary;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * People expected per day over the next two weeks, approved vs. waiting for approval, for one
 * schedule type: the primary one (the shelter) by default, another or all of them from the select.
 */
class ScheduleHeadcountChart extends ChartWidget
{
    private const DAYS = 14;

    private const ALL = 'all';

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '260px';

    public ?string $filter = null;

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('view_any_schedule');
    }

    public function mount(): void
    {
        // the primary type first; with none, every type
        $this->filter ??= (string) (AgendaSummary::primaryType()?->id ?? self::ALL);

        parent::mount();
    }

    public function getHeading(): string | Htmlable | null
    {
        return __('People per day');
    }

    public function getDescription(): string | Htmlable | null
    {
        return __('Next :count days, approved and pending.', ['count' => self::DAYS]);
    }

    protected function getFilters(): ?array
    {
        $types = ScheduleType::query()
            ->orderByDesc('primary')
            ->orderBy('name')
            ->get(['id', 'name', 'primary'])
            ->mapWithKeys(fn(ScheduleType $type) => [(string) $type->id => $type->name])
            ->all();

        return $types + [self::ALL => __('All types')];
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
        $typeId = $this->filter && $this->filter !== self::ALL ? (int) $this->filter : null;
        $days = AgendaSummary::days(CarbonImmutable::today(), self::DAYS, $typeId);

        return [
            'datasets' => [
                [
                    'label' => __('Approved'),
                    'data' => array_column($days, 'approved'),
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => __('Pending'),
                    'data' => array_column($days, 'pending'),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => array_map(fn(array $day) => $day['date']->translatedFormat('D d/m'), $days),
        ];
    }
}
