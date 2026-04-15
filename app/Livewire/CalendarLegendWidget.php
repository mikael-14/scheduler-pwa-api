<?php

namespace App\Livewire;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class CalendarLegendWidget extends Widget
{
    protected string $view = 'livewire.calendar-legend-widget';

    // If you want the widget to span the full width
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        return [
            'items' => collect(\App\Enums\ScheduleStatus::cases())->map(fn($status) => [
                'label' => $status->getLabel(),
                'color' => $status->getColor(),//var(--danger-400)
            ]),
        ];
    }
}
