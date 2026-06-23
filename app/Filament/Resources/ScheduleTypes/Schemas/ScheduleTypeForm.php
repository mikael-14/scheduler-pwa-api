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
                        Toggle::make('status')
                            ->inline(false)
                            ->default(true)
                            ->label(__('Status'))
                            ->helperText(__('Enable or disable this schedule type.')),
                        Flatpickr::make('start')
                            ->allowInput()
                            ->placeholder(__('Start Date'))
                            ->helperText(__('Optional: Set a start date for this schedule type.'))
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
                            ->hourIncrement(1)
                            ->minuteIncrement(5)
                            ->seconds(false)
                            ->format(config('app.date_time_format'))
                            ->altFormat(config('app.date_time_format'))
                            ->time24hr(true),
                        Flatpickr::make('min_time')
                            ->allowInput()
                            ->placeholder(__('Min Time'))
                            ->helperText(__('Optional: Set the minimum hour for schedule each day.'))
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
                            ->helperText(__('Optional: Set the maximum hour for schedule each day.'))
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
                            Checkbox::make('range')
                                ->inline(false)
                                ->default(false)
                                ->label(__('Range'))
                                ->helperText(__('Indicates if this schedule type has to have specific times slots.')),
                            Checkbox::make('all_day')
                                ->inline(false)
                                ->default(false)
                                ->label(__('All Day'))
                                ->helperText(__('Indicates if this schedule type is can have an all-day event.')),
                        ])->columns(2)
                    ])
                    ->columns(2)
                    ->columnSpanFull()
            ]);
    }
}
