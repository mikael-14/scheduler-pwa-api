<?php

namespace App\Filament\Resources\ScheduleTypes\Schemas;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
                            ->label('Status')
                            ->helperText('Enable or disable this schedule type.'),
                        DateTimePicker::make('start')
                            ->placeholder('Start Date')
                            ->helperText('Optional: Set a start date for this schedule type.'),
                        DateTimePicker::make('end')
                            ->placeholder('End Date')
                            ->helperText('Optional: Set an end date for this schedule type.'),
                        Textarea::make('description')
                            ->placeholder('Description'),
                        Flex::make([
                            Checkbox::make('range')
                                ->inline(false)
                                ->default(false)
                                ->label('Range')
                                ->helperText('Indicates if this schedule type has to have specific times slots.'),
                            Checkbox::make('all_day')
                                ->inline(false)
                                ->default(false)
                                ->label('All Day')
                                ->helperText('Indicates if this schedule type is can have an all-day event.'),
                        ])->columns(2)
                    ])
                    ->columns(2)
                    ->columnSpanFull()
            ]);
    }
}
