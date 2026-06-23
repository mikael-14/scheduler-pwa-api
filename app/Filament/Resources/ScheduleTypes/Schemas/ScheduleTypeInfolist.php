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
                    ->collapsed(true)
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('Basic Information')),
                                ColorEntry::make('color')
                                    ->label(__('Color')),
                                IconEntry::make('status')
                                    ->label(__('Status'))
                                    ->boolean(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('range')
                                    ->label(__('Range'))
                                    ->boolean()
                                    ->tooltip(__('Indicates if this schedule type has to have specific times slots.')),
                                IconEntry::make('all_day')
                                    ->label(__('All Day'))
                                    ->boolean()
                                    ->tooltip(__('Indicates if this schedule type is can have an all-day event.')),
                            ]),
                        // Dates
                        TextEntry::make('start')
                            ->label(__('Start'))
                            ->placeholder(__('Not defined'))
                            ->dateTime(config('app.date_time_format'))
                            ->tooltip(__('Set a start date for this schedule type.')),
                        TextEntry::make('end')
                            ->label(__('End'))
                            ->placeholder(__('Not defined'))
                            ->dateTime(config('app.date_time_format'))
                            ->tooltip(__('Set an end date for this schedule type.')),
                        // Times
                        TextEntry::make('min_time')
                            ->label(__('Min time'))
                            ->placeholder(__('Not defined'))
                            ->time(config('app.time_format'))
                            ->tooltip(__('Set the minimum hour for schedule each day.')),
                        TextEntry::make('max_time')
                            ->label(__('Max time'))
                            ->placeholder(__('Not defined'))
                            ->time(config('app.time_format'))
                            ->tooltip(__('Set the maximum hour for schedule each day.')),
                        // Description
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
