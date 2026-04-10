<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;

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
                Select::make('user_id')
                    ->options(User::where('status', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload(true)
                    ->required(),
                Flatpickr::make('start')
                    ->allowInput()
                    ->key(fn (Get $get) => 'start_' . $get('schedule_type_id'))
                    ->visible(fn (Get $get) => $get('schedule_type_id') ?? false)
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
                    ->key(fn (Get $get) => 'end_' . $get('schedule_type_id'))
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
                    ->options(ScheduleStatus::class)
                    ->required()
                    ->columnSpanFull(),

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
