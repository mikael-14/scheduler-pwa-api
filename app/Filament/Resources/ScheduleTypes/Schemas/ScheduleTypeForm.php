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
use Filament\Schemas\Components\Section;

class ScheduleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule Information')
                    ->schema([
                        FusedGroup::make([
                            TextInput::make('name')
                                ->placeholder('name')
                                ->columnSpan(2)
                                ->required(),
                            ColorPicker::make('color')
                                ->placeholder('Select a color')
                                ->default('#000000')
                        ])
                            ->label('Basic Information')
                            ->columns(3),
                        Toggle::make('status')
                            ->inline(false)
                            ->default(true)
                            ->label('Status'),
                        DateTimePicker::make('start')
                            ->placeholder('Start Date'),
                        DateTimePicker::make('end')
                            ->placeholder('End Date'),
                              Textarea::make('description')
                            ->placeholder('Description'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
            ]);
    }
}
