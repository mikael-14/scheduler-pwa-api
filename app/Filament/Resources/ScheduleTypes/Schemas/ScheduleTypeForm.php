<?php

namespace App\Filament\Resources\ScheduleTypes\Schemas;

use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
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
                            ->label('Status')
                            ->helperText('Enable or disable this schedule type.'),
                        Flatpickr::make('start')
                            ->allowInput()
                            ->placeholder('Start Date')
                            ->helperText('Optional: Set a start date for this schedule type.')
                            ->hourIncrement(1) // Intervals of incrementing hours in a time picker
                            ->minuteIncrement(5) // Intervals of minute increment in a time picker
                            ->seconds(false) // Enable seconds in a time picker
                            ->format(config('app.date_time_format'))
                            ->altFormat(config('app.date_time_format'))
                            ->time24hr(true),
                        Flatpickr::make('end')
                            ->allowInput()
                            ->placeholder('End Date')
                            ->helperText('Optional: Set an end date for this schedule type.')
                            ->hourIncrement(1)
                            ->minuteIncrement(5)
                            ->seconds(false)
                            ->format(config('app.date_time_format'))
                            ->altFormat(config('app.date_time_format'))
                            ->time24hr(true),
                        Flatpickr::make('min_time')
                            ->allowInput()
                            ->placeholder('Min Time')
                            ->helperText('Optional: Set the minimum hour for schedule each day.')
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
                            ->placeholder('Max Time')
                            ->helperText('Optional: Set the maximum hour for schedule each day.')
                            ->hourIncrement(1)
                            ->minuteIncrement(5)
                            ->seconds(false)
                            ->format(config('app.time_format'))
                            ->altFormat(config('app.time_format'))
                            ->time24hr(true)
                            ->time(true)
                            ->timePicker(),
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
