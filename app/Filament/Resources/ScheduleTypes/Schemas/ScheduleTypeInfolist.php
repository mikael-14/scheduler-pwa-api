<?php

namespace App\Filament\Resources\ScheduleTypes\Schemas;

use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduleTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Basic Information'),
                                ColorEntry::make('color')
                                    ->label('Color'),
                                IconEntry::make('status')
                                    ->label('Status')
                                    ->boolean(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('range')
                                    ->label('Range')
                                    ->boolean()
                                    ->tooltip('Indicates if this schedule type has to have specific times slots.'),
                                IconEntry::make('all_day')
                                    ->label('All Day')
                                    ->boolean()
                                    ->tooltip('Indicates if this schedule type is can have an all-day event.'),
                            ]),
                        // Dates
                        TextEntry::make('start')
                            ->label('Start')
                            ->placeholder('Not defined')
                            ->dateTime(config('app.date_time_format'))
                            ->tooltip('Set a start date for this schedule type.'),
                        TextEntry::make('end')
                            ->label('End')
                            ->placeholder('Not defined')
                            ->dateTime(config('app.date_time_format'))
                            ->tooltip('Set an end date for this schedule type.'),
                        // Times
                        TextEntry::make('min_time')
                            ->label('Min time')
                            ->placeholder('Not defined')
                            ->time(config('app.time_format'))
                            ->tooltip('Set the minimum hour for schedule each day.'),
                        TextEntry::make('max_time')
                            ->label('Max time')
                            ->placeholder('Not defined')
                            ->time(config('app.time_format'))
                            ->tooltip('Set the maximum hour for schedule each day.'),
                        // Description
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
