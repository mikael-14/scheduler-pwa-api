<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use App\Models\ScheduleUser; // Import the new pivot model
use Filament\Schemas\Schema;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Zvizvi\UserFields\Components\UserSelect;

use function Symfony\Component\Clock\now;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule Information')
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
                    ->label('Select User')
                    ->relationship('user', 'name')
                    ->live()
                    ->searchable()
                    ->preload(true),
                Flatpickr::make('start')
                    ->allowInput()
                    ->key(fn(Get $get) => 'start_' . $get('schedule_type_id'))
                    ->visible(fn(Get $get) => $get('schedule_type_id') ?? false)
                    ->minDate(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        return $config?->start?->format(config('app.date_time_format')) ?? null;
                    })
                    ->maxDate(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        return $config?->end?->format(config('app.date_time_format')) ?? null;
                    })
                    ->time(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        if ($config === null || $config->all_day === false) {
                            return true;
                        } else {
                            $get('all_day') ? false : true;
                        }
                        return true;
                    })
                    ->hourIncrement(1) // Intervals of incrementing hours in a time picker
                    ->minuteIncrement(5) // Intervals of minute increment in a time picker
                    ->seconds(false) // Enable seconds in a time picker
                    ->format(config('app.date_time_format'))
                    ->altFormat(config('app.date_time_format'))
                    ->time24hr(true)
                    ->required(),
                Flatpickr::make('end')
                    ->allowInput()
                    ->key(fn(Get $get) => 'end_' . $get('schedule_type_id'))
                    ->visible(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        return $config?->range ?? false;
                    })
                    ->maxDate(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        return $config?->end?->format(config('app.date_time_format')) ?? null;
                    })
                    ->minDate(function (Get $get) {
                        $start = $get('start');
                        if ($start) {
                            return $start;
                        }
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        return $config?->start?->format(config('app.date_time_format')) ?? null;
                    })
                    ->time(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        if ($config === null || $config->all_day === false) {
                            return true;
                        } else {
                            $get('all_day') ? false : true;
                        }
                        return true;
                    })
                    ->hourIncrement(1) // Intervals of incrementing hours in a time picker
                    ->minuteIncrement(5) // Intervals of minute increment in a time picker
                    ->seconds(false) // Enable seconds in a time picker
                    ->format(config('app.date_time_format'))
                    ->altFormat(config('app.date_time_format'))
                    ->time24hr(true)
                    ->required(),
                Toggle::make('all_day')
                    ->inline(false)
                    ->live()
                    ->visible(function (Get $get) {
                        $config = ScheduleType::find($get('schedule_type_id')) ?? null;
                        return $config?->all_day ?? false;
                    })
                    ->default(false),
                Textarea::make('description')->autosize(),
                Textarea::make('internal_note')->autosize(),
                ToggleButtons::make('status')
                    ->inline()
                    ->default(ScheduleStatus::Pending->value)
                    ->options(ScheduleStatus::class)
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('schedules')
                    ->relationship('schedule_users') // Use the new hasMany relationship to the pivot model
                    ->reorderable(false)
                    ->schema([
                        UserSelect::make('user_id')
                            ->label('Select User')
                            ->relationship('user', 'name') // Relationship from ScheduleUser to User
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->disableOptionWhen(function ($value, Get $get) {
                                $mainUserId = $get('../../user_id');
                                return $value && $mainUserId && (string) $value === (string) $mainUserId;
                            }, merge: true)
                            ->searchable()
                            ->preload(true),
                         Select::make('status')
                            ->searchable()
                            ->default(ScheduleStatus::Pending->value)
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->options(ScheduleStatus::class)
                            ->suffixIcon(fn ($state) => ($state instanceof ScheduleStatus ? $state : ScheduleStatus::tryFrom((string) $state))?->getIcon() ?? Heroicon::QuestionMarkCircle)
                            ->suffixIconColor(fn ($state) => ($state instanceof ScheduleStatus ? $state : ScheduleStatus::tryFrom((string) $state))?->getColor() ?? 'gray')
                            ->required(),
                        Textarea::make('description')->autosize()->rows(1),
                       
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): ?array {
                        $mainUserId = $get('user_id');
                        // If the user selected in the repeater item is the same as the main user, return null to exclude it
                        if ($mainUserId && isset($data['user_id']) && (string) $data['user_id'] === (string) $mainUserId) {
                            return null;
                        }
                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): ?array {
                        $mainUserId = $get('user_id');
                        // Prevent saving an existing item if it now matches the main user ID
                        if ($mainUserId && isset($data['user_id']) && (string) $data['user_id'] === (string) $mainUserId) {
                            return null;
                        }
                        return $data;
                    })
                    ->columns(3)
                    ->columnSpanFull()

            ];
    }
    public static function getOptionWithColor(\Illuminate\Database\Eloquent\Collection $model)
    {
        return $model->mapWithKeys(function ($item) {
            return [$item['id'] => view('filament.components.select-with-color')
                ->with('name', $item['name'])
                ->with('color', $item['color'])
                ->render()];
        });
    }
}
