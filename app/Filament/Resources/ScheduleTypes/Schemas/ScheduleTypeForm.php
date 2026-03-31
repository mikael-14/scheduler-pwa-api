<?php

namespace App\Filament\Resources\ScheduleTypes\Schemas;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Schema;
use Symfony\Component\Console\Color;

class ScheduleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FusedGroup::make([
                    TextInput::make('name')
                        ->placeholder('name')
                        ->columns(2)
                        ->required(),
                    ColorPicker::make('color')
                        ->placeholder('Select a color'),
                ])
                ->label('Basic Information')
                ->columns(3),
                Toggle::make('status')
                    ->inline(false)
                    ->default(true)
                    ->label('Status'),
                Select::make('type')
                    ->options(ScheduleType::cases())
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('start', null)->set('end', null))
                    ->placeholder('None'),
                Textarea::make('description')
                    ->placeholder('Description'),
                DateTimePicker::make('start')
                    ->placeholder('Start Date')
                    ->visible(fn ($get) => in_array($get('type'), [ScheduleType::Start, ScheduleType::Range]))
                    ->required(fn ($get) => in_array($get('type'), [ScheduleType::Start, ScheduleType::Range])),
                DateTimePicker::make('end')
                    ->placeholder('End Date')
                    ->visible(fn ($get) => in_array($get('type'), [ScheduleType::End, ScheduleType::Range]))
                    ->required(fn ($get) => in_array($get('type'), [ScheduleType::End, ScheduleType::Range])),
            ]);
    }
}
