<?php

namespace App\Livewire;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions\EditAction;

class CalendarWidget extends FullCalendarWidget
{
    public Model|int|string|null $record = null;

    protected int | string | array $columnSpan = 'full'; // can be set to 'full' or a specific number of columns;

    public function fetchEvents(array $fetchInfo): array
    {
        return Schedule::query()
            ->where('schedule_type_id', $this->record->id)
            ->whereBetween('start', [
                $fetchInfo['start'],
                $fetchInfo['end'],
            ])
            ->get()
            ->map(function ($event) {
                // 1. Get the color key from the Enum (e.g., 'warning')
                $colorKey = $event->status->getColor();
                // 2. Wrap it in the CSS variable syntax
                $backgroundColor = "var(--{$colorKey}-600)";
                return [
                    'id'    => $event->id,
                    'title' => $event->user->name . ($event->all_day ? ' (All Day)' : ''),
                    'start' => $event->start,
                    'end'   => $event->end,
                    'allDay' => $event->all_day,
                    'backgroundColor' => $backgroundColor,
                    'borderColor'     => $backgroundColor,
                ];
            })
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
            'eventDisplay' => 'block',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'eventTimeFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'meridiem' => false,
                'hour12' => false, // Set to false for 24-hour format
            ],

        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make('edit')
                ->record(fn(array $arguments) => Schedule::find($arguments['event'])) // Fetch the right model
                ->mountUsing(function (Schedule $record, Schema $form) {
                    return $form->fill([
                        'schedule_type_id' => $record->schedule_type_id,
                        'user_id' => $record->user_id,
                        'start'   => $record->start,
                        'end'     => $record->end,
                        'all_day' => $record->all_day,
                        'description' => $record->description,
                        'internal_note' => $record->internal_note,
                        'status' => $record->status,
                    ]);
                }),
        ];
    }

    public function onEventClick(array $info): void
    {
        $this->mountAction('edit', [
            'event' => $info['id'],
        ]);
    }
}
