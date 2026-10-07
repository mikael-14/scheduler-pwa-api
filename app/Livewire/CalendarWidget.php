<?php

namespace App\Livewire;

use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Models\Schedule;
use Filament\Schemas\Components\Grid;
use Livewire\Attributes\On;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction;

class CalendarWidget extends FullCalendarWidget
{
    public Model|int|string|null $record = null; // Holds the ScheduleType model from the page

    protected int | string | array $columnSpan = 'full';

    public array $filterStatus = [];
    public array $filterUserIds = [];

    public function getModel(): string
    {
        return Schedule::class;
    }

    protected function headerActions(): array
    {
        return [
            CreateAction::make()
                ->model(Schedule::class)
                ->schema([
                    Grid::make(2)
                        ->schema(ScheduleForm::formSchema())
                ])
                ->mountUsing(function (Schema $form) {
                    $form->model(new Schedule());
                    $form->fill([
                        'schedule_type_id' => $this->record?->id,
                    ]);
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make('edit')
                ->model(Schedule::class)
                ->record(fn(array $arguments) => Schedule::with('schedule_users')->find($arguments['event']))
                ->schema([
                    Grid::make(2)
                        ->schema(ScheduleForm::formSchema())
                ])
                ->mountUsing(function (Schedule $record, Schema $form) {
                    $form->model($record);
                    $form->fill([
                        'schedule_type_id' => $record->schedule_type_id,
                        'user_id'          => $record->user_id,
                        'start'            => $record->start,
                        'end'              => $record->end,
                        'all_day'          => $record->all_day,
                        'description'      => $record->description,
                        'internal_note'    => $record->internal_note,
                        'status'           => $record->status,
                    ]);
                }),
        ];
    }

    public function getFormSchema(): array
    {
        // Left empty so the root widget doesn't evaluate ScheduleForm against ScheduleType ($this->record)
        return [];
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
                $colorKey = $event->status->getColor();
                $backgroundColor = "var(--{$colorKey}-600)";

                $hasComment = !empty($event->description);

                $title = ($event->all_day ? '⌛ ' : '') . $event->user->name;
                if ($hasComment) {
                    $title = $title . ' 💬';
                }

                return [
                    'id'    => $event->id,
                    'title' => $title,
                    'start' => $event->start,
                    'end'   => $event->end,
                    'allDay' => $event->all_day,
                    'backgroundColor' => $backgroundColor,
                    'borderColor'     => $backgroundColor,
                    'description' => $event->description,
                ];
            })
            ->toArray();
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

        if ($this->record?->min_time) {
            $allConfig['slotMinTime'] = $this->record->min_time->format('H:i:s');
        }
        if ($this->record?->max_time) {
            $allConfig['slotMaxTime'] = $this->record->max_time->format('H:i:s');
        }
        return $allConfig;
    }

    public function eventDidMount(): string
    {
        return <<<JS
        function({ event, timeText, isStart, isEnd, isMirror, isPast, isFuture, isToday, el, view }){
            const description = event.extendedProps.description;

            if (description) {
                el.setAttribute("x-tooltip", "tooltip");
                el.setAttribute("x-data", "{ tooltip: " + JSON.stringify(description) + " }");
            }
        }
        JS;
    }

    public function onEventClick(array $info): void
    {
        $this->mountAction('edit', [
            'event' => $info['id'],
        ]);
    }
}
