<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User information')
                    ->schema([
                        DatePicker::make('date')
                            ->format(config('app.date_format'))
                            ->displayFormat(config('app.date_format'))
                            ->default(now())
                            ->required(),
                        TimePicker::make('time')
                            ->format(config('app.time_format'))
                            ->seconds(false)
                            ->default(now())
                            ->native()
                            ->step(15)
                            ->required(),
                        Textarea::make('description')->autosize(),
                        Textarea::make('internal_note')->autosize(),
                        ToggleButtons::make('status')
                            ->inline()
                            ->options(ScheduleStatus::class)
                            ->required(),
                        Select::make('user_id')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->preload(true)
                            ->required(),
                        Select::make('schedule_type_id')
                            ->options(self::getOptionWithColor(ScheduleType::all()))
                            ->searchable()
                            ->allowHtml(true)
                            ->native(false)
                            ->preload(true)
                            ->required(),
                    ])->columns(2)
                    ->columnSpan('full'),
            ]);
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
