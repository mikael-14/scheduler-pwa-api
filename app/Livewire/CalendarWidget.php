<?php

namespace App\Livewire;

use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;

class CalendarWidget extends FullCalendarWidget
{
    public Model|int|string|null $record = null;

    public function fetchEvents(array $fetchInfo): array
    {
        return Schedule::query()
            ->where('schedule_type_id', $this->record->id)
            ->whereBetween('start_date', [
                $fetchInfo['start'],
                $fetchInfo['end'],
            ])
            ->get()
            ->map(fn($event) => [
                'id' => $event->id,
                'title' => $event->name,
                'start' => $event->start_date . ' ' . $event->start_time,
                'end' => $event->end_date . ' ' . $event->end_time,
                'backgroundColor' => $this->record->color ?? 'var(--primary-600)',
            ])
            ->toArray();
    }

    public function getFormSchema(): array
    {
        return [
            Grid::make()
                ->schema(ScheduleForm::formSchema()),
        ];
    }
    public function config(): array
    {
        return [
            'initialView' => 'dayGridMonth',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
        ];
    }
}
