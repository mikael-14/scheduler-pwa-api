<?php

namespace App\Livewire;

use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Models\Schedule;
use Livewire\Attributes\On;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions\EditAction;

class CalendarWidget extends FullCalendarWidget
{

    public Model|int|string|null $record = null;

    protected int | string | array $columnSpan = 'full'; // can be set to 'full' or a specific number of columns;

    public array $filterStatus = [];
    public array $filterUserIds = [];

    public function getModel(): string
    {
        return Schedule::class;
    }

    protected function headerActions(): array
    {
        return [
            \Saade\FilamentFullCalendar\Actions\CreateAction::make()
                ->model(Schedule::class) // Explicitly set the model here
                ->mountUsing(function (Schema $form) {
                    // Pre-fill fields if needed, e.g., the schedule type
                    return $form->fill([
                        'schedule_type_id' => $this->record->id,
                    ]);
                }),
        ];
    }

    #[On('filterCalendar')]
    public function updateFilter($status = [], $userIds = []): void
    {
        $this->filterStatus = $status;
        $this->filterUserIds = $userIds;

        // This tells the frontend JS: "Go call the fetchEvents method again"
        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return Schedule::query()
            ->where('schedule_type_id', $this->record->id)
            ->when(!empty($this->filterStatus), fn($query) => $query->whereIn('status', $this->filterStatus))
            ->when(!empty($this->filterUserIds), fn($query) => $query->whereIn('user_id', $this->filterUserIds))
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
        $allConfig = [
            'height' => 'auto',
            'initialView' => 'timeGridWeek',
            'eventDisplay' => 'block',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'slotDuration' => '01:00:00',
            'eventTimeFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'meridiem' => false,
                'hour12' => false, // Set to false for 24-hour format
            ],
        ];
        if ($this->record->min_time) {
            $allConfig['slotMinTime'] = $this->record->min_time->format('H:i:s');
        }
        if ($this->record->max_time) {
            $allConfig['slotMaxTime'] = $this->record->max_time->format('H:i:s');
        }
        return $allConfig;
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
