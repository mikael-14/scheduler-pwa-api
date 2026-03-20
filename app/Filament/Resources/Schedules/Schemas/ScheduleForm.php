<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
                            ->required(),
                        TimePicker::make('time')
                            ->format(config('app.time_format'))
                            ->seconds(false)
                            ->native()
                            ->step(15)
                            ->required(),
                        TextInput::make('description'),
                        TextInput::make('internal_note'),
                        Select::make('status')
                            ->options(ScheduleStatus::class)
                            ->default(ScheduleStatus::Pending)
                            ->searchable()
                            ->preload(true)
                            ->required(),
                        Select::make('user_id')
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->preload(true)
                            ->required(),
                        Select::make('schedule_type_id')
                            ->options(ScheduleType::pluck('name', 'id'))
                            ->searchable()
                            ->preload(true)
                            ->required(),
                    ])->columns(2)
                    ->columnSpan('full'),
            ]);
    }
}
