<?php

namespace App\Livewire;

use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Services\ScheduleAccess;
use App\Services\ScheduleTypeRules;
use Filament\Schemas\Components\Grid;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use App\Filament\Resources\Schedules\ScheduleResource;
use Illuminate\Support\Facades\Gate;

class CalendarWidget extends FullCalendarWidget
{
    // Holds the ScheduleType model on a type page; null on the central calendar
    public Model|int|string|null $record = null;

    protected int | string | array $columnSpan = 'full';

    public array $filterStatus = [];
    public array $filterUserIds = [];
    public array $filterTypeIds = [];
    public string $colorBy = 'status'; // 'status' | 'type'

    // A day to open on, in day view (YYYY-MM-DD, from the dashboard's links); null = today, week view
    public ?string $initialDate = null;

    public function getModel(): string
    {
        return Schedule::class;
    }

    protected function headerActions(): array
    {
        return [
            CreateAction::make()
                ->model(Schedule::class)
                // the modal's title and buttons say "â€¦ agendamento", not the class name
                ->modelLabel(mb_strtolower(__('Schedule')))
                ->label(__('Create Schedule'))
                // a date click / select opens it too: the same permission as the Create page
                ->authorize(fn() => Gate::allows('create', Schedule::class))
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
                ->modelLabel(mb_strtolower(__('Schedule')))
                ->record(fn(array $arguments) => $this->visibleSchedule($arguments['event'] ?? null))
                // also reached by drag / resize requests: only who may edit it (SchedulePolicy::update)
                ->authorize(fn(?Schedule $record) => $record && Gate::allows('update', $record))
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
                        // staff only: for others it never reaches the browser
                        ...(ScheduleAccess::seesInternalNote(auth()->user()) ? ['internal_note' => $record->internal_note] : []),
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
    public function updateFilter($status = [], $userIds = [], $typeIds = [], $colorBy = 'status'): void
    {
        $this->filterStatus = $status ?? [];
        $this->filterUserIds = $userIds ?? [];
        $this->filterTypeIds = $typeIds ?? [];
        $this->colorBy = in_array($colorBy, ['status', 'type'], true) ? $colorBy : 'status';

        // This tells the frontend JS: "Go call the fetchEvents method again"
        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $tz = config('app.timezone');
        $from = Carbon::parse($fetchInfo['start'])->setTimezone($tz);
        $to = Carbon::parse($fetchInfo['end'])->setTimezone($tz);
        $typeId = $this->record?->id;

        return \App\Services\ScheduleAccess::scopeVisible(Schedule::query(), auth()->user())
            ->with(['user', 'schedule_type', 'schedule_users.user'])
            ->when($typeId, fn($query) => $query->where('schedule_type_id', $typeId))
            ->when(!$typeId && !empty($this->filterTypeIds), fn($query) => $query->whereIn('schedule_type_id', $this->filterTypeIds))
            ->when(!empty($this->filterStatus), fn($query) => $query->whereIn('status', $this->filterStatus))
            ->when(!empty($this->filterUserIds), fn($query) => $query->where(function ($q) {
                $q->whereIn('user_id', $this->filterUserIds)
                    ->orWhereHas('schedule_users', fn($su) => $su->whereIn('user_id', $this->filterUserIds));
            }))
            // Overlap, not just "starts inside": includes multi-day schedules that began before the window
            ->where('start', '<', $to)
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$from])
            ->get()
            ->map(function (Schedule $event) {
                $type = $event->schedule_type;

                if ($this->colorBy === 'type' && $type?->color) {
                    $backgroundColor = $type->color;
                } else {
                    $backgroundColor = 'var(--' . ($event->status?->getColor() ?? 'gray') . '-600)';
                }

                $names = collect([$event->user?->name])
                    ->merge($event->schedule_users->map(fn($su) => $su->user?->name))
                    ->filter()
                    ->unique()
                    ->values();

                $title = $names->first() ?? __('No user');
                if ($names->count() > 1) {
                    $title .= ' +' . ($names->count() - 1);
                }
                if ($event->all_day) {
                    $title = 'âŒ› ' . $title;
                }
                if (!empty($event->description)) {
                    $title .= ' ðŸ’¬';
                }
                // On the central calendar, say which type each event belongs to
                if (!$this->record && $type) {
                    $title .= ' Â· ' . $type->name;
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
            // the week/day row and the list's label for schedules without hours, as the rest of the panel
            'allDayText' => __('No time'),
            'slotDuration' => '01:00:00',
            'eventTimeFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'meridiem' => false,
                'hour12' => false, // Set to false for 24-hour format
            ],
        ];

        // Visible hours (display only): the type's own on its page, otherwise from the earliest
        // to the latest of the active types, so no type is cut off on the central calendar
        $hours = $this->record instanceof ScheduleType
            ? (new ScheduleTypeRules($this->record))->calendarHours()
            : ScheduleTypeRules::calendarHoursFor(ScheduleTypeRules::activeTypes());

        if ($hours['min']) {
            $allConfig['slotMinTime'] = $hours['min'];
        }
        if ($hours['max']) {
            $allConfig['slotMaxTime'] = $hours['max'];
        }
        if ($this->initialDate) {
            $allConfig['initialDate'] = $this->initialDate;
            $allConfig['initialView'] = 'timeGridDay';
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

    /**
     * A click opens the edit popup for who may edit the schedule (SchedulePolicy::update: their own
     * from today on, or anyone's with update_any_schedule); anyone else who may see it gets its page.
     */
    public function onEventClick(array $info): void
    {
        $schedule = $this->visibleSchedule($info['id'] ?? null);

        if (! $schedule) {
            return;
        }

        if (Gate::allows('update', $schedule)) {
            $this->mountAction('edit', [
                'event' => $schedule->id,
            ]);

            return;
        }

        if (Gate::allows('view', $schedule)) {
            $this->redirect(ScheduleResource::getUrl('infolist', ['record' => $schedule]));
        }
    }

    /** The schedule behind a calendar event (an id, or FullCalendar's event object), if the user sees it. */
    private function visibleSchedule(mixed $event): ?Schedule
    {
        $id = is_array($event) ? ($event['id'] ?? null) : $event;
        $user = auth()->user();

        if (! $id || ! $user) {
            return null;
        }

        return ScheduleAccess::scopeVisible(Schedule::query(), $user)->with('schedule_users')->find($id);
    }
}
