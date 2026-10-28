<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleType;
use App\Models\User;
use App\Services\ScheduleTypeRules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use App\Models\ScheduleUser; // Import the new pivot model
use Filament\Schemas\Schema;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Zvizvi\UserFields\Components\UserSelect;


class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Schedule Information'))
                    ->schema(self::formSchema())->columns(2)
                    ->columnSpan('full'),
            ]);
    }

    public static function formSchema(): array
    {
        return
            [
                Select::make('schedule_type_id')
                    ->options(self::getOptionWithColor(ScheduleType::all()))
                    ->searchable()
                    ->live()
                    ->allowHtml(true)
                    ->native(false)
                    ->preload(true)
                    ->required(),
                UserSelect::make('user_id')
                    ->label(__('Select User'))
                    ->relationship('user', 'name')
                    ->live()
                    ->searchable()
                    ->preload(true),
                // The type's rules (ScheduleTypeRules), also enforced on save by ScheduleRulesValidator:
                // the pickers stop at the type's start / end, "all day" only when the type allows it,
                // an end only when the type needs one.
                Flatpickr::make('start')
                    ->allowInput()
                    // a new picker when the type or "all day" changes (limits and time picker differ)
                    ->key(fn(Get $get) => 'start_' . $get('schedule_type_id') . '_' . (int) self::isAllDay($get))
                    ->visible(fn(Get $get) => $get('schedule_type_id') ?? false)
                    ->minDate(fn(Get $get) => self::pickerDate(self::rules($get)?->windowStart(), $get))
                    ->maxDate(fn(Get $get) => self::pickerDate(self::rules($get)?->windowEnd(), $get))
                    ->time(fn(Get $get) => ! self::isAllDay($get))
                    ->hourIncrement(1) // Intervals of incrementing hours in a time picker
                    ->minuteIncrement(5) // Intervals of minute increment in a time picker
                    ->seconds(false) // Enable seconds in a time picker
                    ->format(fn(Get $get) => self::pickerFormat($get))
                    ->altFormat(fn(Get $get) => self::pickerFormat($get))
                    ->time24hr(true)
                    ->live()
                    ->required(),
                Flatpickr::make('end')
                    ->allowInput()
                    ->key(fn(Get $get) => 'end_' . $get('schedule_type_id') . '_' . (int) self::isAllDay($get))
                    ->visible(fn(Get $get) => self::rules($get)?->requiresEnd() ?? false)
                    ->maxDate(fn(Get $get) => self::pickerDate(self::rules($get)?->windowEnd(), $get))
                    // never before the start
                    ->minDate(fn(Get $get) => $get('start') ?: self::pickerDate(self::rules($get)?->windowStart(), $get))
                    ->time(fn(Get $get) => ! self::isAllDay($get))
                    ->hourIncrement(1) // Intervals of incrementing hours in a time picker
                    ->minuteIncrement(5) // Intervals of minute increment in a time picker
                    ->seconds(false) // Enable seconds in a time picker
                    ->format(fn(Get $get) => self::pickerFormat($get))
                    ->altFormat(fn(Get $get) => self::pickerFormat($get))
                    ->time24hr(true)
                    ->required(),
                Toggle::make('all_day')
                    ->label(__('No time'))
                    ->inline(false)
                    ->live()
                    // only on types that allow it, for people who may give no hours (AllDayRule)
                    ->visible(fn(Get $get, $record) => (self::rules($get)?->allowsAllDay() ?? false)
                        && (\App\Services\AllDayRule::mayBook(Filament::auth()->user()) || $record?->all_day))
                    ->default(false),
                Textarea::make('description')->autosize(),
                Textarea::make('internal_note')->autosize(),
                ToggleButtons::make('status')
                    ->inline()
                    ->default(ScheduleStatus::Pending->value)
                    ->options(ScheduleStatus::class)
                    ->required()
                    // the same rules as everywhere (ScheduleAccess::mayChangeStatus, enforced on save):
                    // completed only by the system, cancelled by its creator before it starts,
                    // not shown by admins once it started, approved / rejected by approvers
                    ->disableOptionWhen(function ($value, $record) {
                        $schedule = $record instanceof \App\Models\Schedule
                            ? $record
                            : new \App\Models\Schedule(['user_id' => Filament::auth()->id()]);

                        return ! \App\Services\ScheduleAccess::mayChangeStatus(
                            Filament::auth()->user(),
                            $schedule,
                            $schedule->exists ? $schedule->status : null,
                            $value instanceof ScheduleStatus ? $value : ScheduleStatus::from($value),
                        );
                    }, merge: true)
                    ->columnSpanFull(),
                Repeater::make('schedules')
                    ->defaultItems(0)
                    ->relationship('schedule_users') // Use the new hasMany relationship to the pivot model
                    ->reorderable(false)
                    ->schema([
                        UserSelect::make('user_id')
                            ->label(__('Select User'))
                            ->relationship('user', 'name') // Relationship from ScheduleUser to User
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->disableOptionWhen(function ($value, Get $get) {
                                $mainUserId = $get('../../user_id', false);
                                return $value && $mainUserId && (string) $value === (string) $mainUserId;
                            }, merge: true)
                            ->searchable()
                            ->required()
                            ->preload(true),
                        Select::make('status')
                            ->searchable()
                            ->default(ScheduleStatus::Pending->value)
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->options(ScheduleStatus::class)
                            // completed only by the system; not shown by the owner once it started
                            ->disableOptionWhen(function ($value, $record) {
                                $participant = $record instanceof ScheduleUser ? $record : new ScheduleUser();

                                return ! \App\Services\ScheduleAccess::mayChangeParticipantStatus(
                                    Filament::auth()->user(),
                                    $participant,
                                    $participant->exists ? $participant->status : null,
                                    $value instanceof ScheduleStatus ? $value : ScheduleStatus::from($value),
                                );
                            }, merge: true)
                            ->suffixIcon(fn($state) => ($state instanceof ScheduleStatus ? $state : ScheduleStatus::tryFrom((string) $state))?->getIcon() ?? Heroicon::QuestionMarkCircle)
                            ->suffixIconColor(fn($state) => ($state instanceof ScheduleStatus ? $state : ScheduleStatus::tryFrom((string) $state))?->getColor() ?? 'gray')
                            ->required(),
                        Textarea::make('description')->autosize()->rows(1),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): ?array {
                        $mainUserId = $get('../../user_id');

                        if ($mainUserId && isset($data['user_id']) && (string) $data['user_id'] === (string) $mainUserId) {
                            return null;
                        }

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): ?array {
                        $mainUserId = $get('../../user_id');

                        if ($mainUserId && isset($data['user_id']) && (string) $data['user_id'] === (string) $mainUserId) {
                            return null;
                        }

                        return $data;
                    })
                    ->defaultItems(0)
                    ->addActionLabel(__('Add Participant'))
                    ->columns(3)
                    ->columnSpanFull()

            ];
    }

    /** The rules of the type chosen in the form (looked up once per request). */
    private static function rules(Get $get): ?ScheduleTypeRules
    {
        return ScheduleTypeRules::for($get('schedule_type_id'));
    }

    /** "All day" counts only when the type allows it. */
    private static function isAllDay(Get $get): bool
    {
        return (bool) $get('all_day') && (self::rules($get)?->allowsAllDay() ?? false);
    }

    /** Date only for all-day schedules, date and time otherwise. */
    private static function pickerFormat(Get $get): string
    {
        return self::isAllDay($get) ? config('app.date_format') : config('app.date_time_format');
    }

    private static function pickerDate(?\Carbon\CarbonInterface $moment, Get $get): ?string
    {
        return $moment?->format(self::pickerFormat($get));
    }

    /** Each type with its colour; those whose end has passed last, tagged "Ended" (still selectable). */
    public static function getOptionWithColor(\Illuminate\Database\Eloquent\Collection $model)
    {
        return $model
            ->sortBy(fn(ScheduleType $type) => (new ScheduleTypeRules($type))->hasEnded() ? 1 : 0)
            ->mapWithKeys(function (ScheduleType $item) {
                return [$item['id'] => view('filament.components.select-with-color')
                    ->with('name', $item['name'])
                    ->with('color', $item['color'])
                    ->with('ended', (new ScheduleTypeRules($item))->hasEnded())
                    ->render()];
            });
    }
}
