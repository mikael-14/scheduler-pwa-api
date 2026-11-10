<?php

namespace App\Filament\Resources\ScheduleTypes\Schemas;

use App\Models\ScheduleType;
use App\Models\ScheduleTypeSlot;
use App\Services\SlotDefinitions;
use Carbon\Carbon;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class ScheduleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Schedule Information'))
                    ->schema([
                        FusedGroup::make([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->placeholder(__('name'))
                                ->columnSpan(2)
                                ->required(),
                            ColorPicker::make('color')
                                ->placeholder(__('Select a color'))
                                ->label(__('Color'))
                                ->default('#000000')
                        ])
                            ->label(__('Basic Information'))
                            ->columns(3),
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                Toggle::make('status')
                                    ->inline(false)
                                    ->default(true)
                                    ->label(__('Active'))
                                    ->helperText(__('Enable or disable this schedule type.')),
                                // "primary" is not edited here: it is set in the database (only one type has it)
                            ]),
                        Flatpickr::make('start')
                            ->allowInput()
                            ->placeholder(__('Start Date'))
                            ->helperText(__('Optional: Set a start date for this schedule type.'))
                            ->time(true) // the date and the hour (without it the picker only shows days)
                            ->hourIncrement(1) // Intervals of incrementing hours in a time picker
                            ->minuteIncrement(5) // Intervals of minute increment in a time picker
                            ->seconds(false) // Enable seconds in a time picker
                            ->format(config('app.date_time_format'))
                            ->altFormat(config('app.date_time_format'))
                            ->time24hr(true),
                        Flatpickr::make('end')
                            ->allowInput()
                            ->placeholder(__('End Date'))
                            ->helperText(__('Optional: Set an end date for this schedule type.'))
                            ->time(true)
                            ->hourIncrement(1)
                            ->minuteIncrement(5)
                            ->seconds(false)
                            ->format(config('app.date_time_format'))
                            ->altFormat(config('app.date_time_format'))
                            ->time24hr(true),
                        Flatpickr::make('min_time')
                            ->allowInput()
                            ->placeholder(__('Min Time'))
                            ->helperText(__('Optional: schedules cannot start before this time (the calendar starts here).'))
                            ->hourIncrement(1)
                            ->minuteIncrement(5)
                            ->seconds(false)
                            ->format(config('app.time_format'))
                            ->altFormat(config('app.time_format'))
                            ->time24hr(true)
                            ->time(true)
                            ->timePicker(),
                        Flatpickr::make('max_time')
                            ->allowInput()
                            ->placeholder(__('Max Time'))
                            ->helperText(__('Optional: schedules must end by this time (the calendar ends here). 00:00 = midnight.'))
                            ->hourIncrement(1)
                            ->minuteIncrement(5)
                            ->seconds(false)
                            ->format(config('app.time_format'))
                            ->altFormat(config('app.time_format'))
                            ->time24hr(true)
                            ->time(true)
                            ->timePicker(),
                        Textarea::make('description')
                            ->placeholder(__('Description')),
                        Flex::make([
                            Toggle::make('range')
                                ->inline(false)
                                ->default(true)
                                ->label(__('Needs an end time'))
                                ->helperText(__('Schedules of this type have a start and an end time.')),
                            Toggle::make('all_day')
                                ->inline(false)
                                ->default(false)
                                ->label(__('No time'))
                                ->helperText(__('Whether schedules of this type can be booked without hours.')),
                        ])->columns(2)
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                // Shifts: people pick one of these instead of their own hours (ScheduleSlots)
                Section::make(__('Slots'))
                    ->description(__('Fixed times people pick, like shifts. The first person to pick a slot books it; the next ones join that schedule.'))
                    ->schema([
                        Toggle::make('uses_slots')
                            ->label(__('Members pick a slot'))
                            ->helperText(__('Schedules of this type can only be booked on one of the slots below.'))
                            ->live(),
                        Repeater::make('slots')
                            ->label(__('Slots'))
                            ->relationship('slots', fn($query) => $query->reorder()->orderBy('start'))
                            ->visible(fn(Get $get) => (bool) $get('uses_slots'))
                            ->schema([
                                DatePicker::make('day')
                                    ->label(__('Day'))
                                    ->native(false)
                                    ->displayFormat(config('app.date_format'))
                                    ->disabled(fn($record) => self::isTaken($record))
                                    ->required(),
                                TimePicker::make('from')
                                    ->label(__('Start'))
                                    ->seconds(false)
                                    ->disabled(fn($record) => self::isTaken($record))
                                    ->required(),
                                TimePicker::make('to')
                                    ->label(__('End'))
                                    ->seconds(false)
                                    ->helperText(__('00:00 = midnight'))
                                    ->disabled(fn($record) => self::isTaken($record))
                                    ->required()
                                    ->rule(fn(Get $get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        if (self::isTaken($record)) {
                                            return;
                                        }
                                        if ($problem = self::slotProblem($get, $get('day'), $get('from'), $value)) {
                                            $fail($problem);
                                        }
                                    }),
                                TextInput::make('capacity')
                                    ->label(__('Max people'))
                                    ->placeholder(__('No limit'))
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(1000),
                                Textarea::make('comment')
                                    ->label(__('Comment'))
                                    ->placeholder(__('Information for the people on this slot'))
                                    ->autosize()
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->cloneable()
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn(array $state) => self::itemLabel($state))
                            ->addActionLabel(__('Add slot'))
                            // a slot with a schedule going on stays (its comment and maximum can change)
                            ->deleteAction(fn(Action $action) => $action->hidden(
                                fn(array $arguments, Repeater $component) => self::isTaken(self::itemRecord($component, $arguments['item'] ?? null))
                            ))
                            ->mutateRelationshipDataBeforeFillUsing(fn(array $data) => self::toForm($data))
                            ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => self::fromForm($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn(array $data) => self::fromForm($data)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function isTaken(mixed $record): bool
    {
        return $record instanceof ScheduleTypeSlot && $record->isTaken();
    }

    /** The slot record of a repeater item ("record-12"), if it is saved already. */
    private static function itemRecord(Repeater $component, ?string $key): ?ScheduleTypeSlot
    {
        if (! $key || ! preg_match('/^record-(\d+)$/', $key, $match)) {
            return null;
        }

        return ScheduleTypeSlot::find((int) $match[1]);
    }

    /** The stored start / end as a day and two times. */
    private static function toForm(array $data): array
    {
        $tz = config('app.timezone');
        $start = Carbon::parse($data['start'])->setTimezone($tz);
        $end = Carbon::parse($data['end'])->setTimezone($tz);

        return $data + ['day' => $start->format('Y-m-d'), 'from' => $start->format('H:i'), 'to' => $end->format('H:i')];
    }

    /** A day and two times back to start / end (an end at or before the start is the next day). */
    private static function fromForm(array $data): array
    {
        if (filled($data['day'] ?? null) && filled($data['from'] ?? null) && filled($data['to'] ?? null)) {
            [$start, $end] = self::moments($data['day'], $data['from'], $data['to']);
            $data['start'] = $start;
            $data['end'] = $end;
        }
        $data['capacity'] = filled($data['capacity'] ?? null) ? (int) $data['capacity'] : null;

        return collect($data)->except(['day', 'from', 'to'])->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function moments(string $day, string $from, string $to): array
    {
        return SlotDefinitions::moments($day, $from, $to);
    }

    /** Why a slot does not fit (the end before the start, the type's dates or daily hours), or null. */
    private static function slotProblem(Get $get, mixed $day, mixed $from, mixed $to): ?string
    {
        if (! filled($day) || ! filled($from) || ! filled($to)) {
            return null;
        }

        [$start, $end] = self::moments((string) $day, (string) $from, (string) $to);

        // the type as it is in the form (not saved yet)
        $moment = function (string $field) use ($get): ?Carbon {
            try {
                return filled($value = $get('../../' . $field)) ? Carbon::parse($value, config('app.timezone')) : null;
            } catch (\Throwable) {
                return null; // the type's own fields report a bad value
            }
        };
        $type = new ScheduleType(['name' => $get('../../name') ?: __('This type')]);
        foreach (['start', 'end', 'min_time', 'max_time'] as $field) {
            $type->{$field} = $moment($field);
        }
        return SlotDefinitions::fitProblem($type, $start, $end);
    }

    /** "21/11/2026 · 09:00–11:00 · max 5" as the item's title. */
    private static function itemLabel(array $state): ?string
    {
        if (! filled($state['day'] ?? null)) {
            return __('New slot');
        }

        $label = Carbon::parse($state['day'])->format(config('app.date_format'))
            . ' · ' . substr((string) ($state['from'] ?? ''), 0, 5) . '–' . substr((string) ($state['to'] ?? ''), 0, 5);

        return filled($state['capacity'] ?? null) ? $label . ' · ' . __('max :count', ['count' => $state['capacity']]) : $label;
    }
}
