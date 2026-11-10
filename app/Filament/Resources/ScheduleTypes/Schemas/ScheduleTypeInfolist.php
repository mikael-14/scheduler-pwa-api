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
                Section::make(__('Details'))
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
                                    ->label(__('Active'))
                                    ->boolean(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('range')
                                    ->label(__('Needs an end time'))
                                    ->boolean()
                                    ->tooltip(__('Schedules of this type have a start and an end time.')),
                                IconEntry::make('all_day')
                                    ->label(__('No time'))
                                    ->boolean()
                                    ->tooltip(__('Whether schedules of this type can be booked without hours.')),
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
                // shifts: the slots people pick, with how many are on each
                Section::make(__('Slots'))
                    ->visible(fn($record) => (bool) $record?->uses_slots)
                    ->schema([
                        TextEntry::make('slot_list')
                            ->hiddenLabel()
                            ->state(fn($record) => $record->slots()->get()
                                ->map(fn($slot) => \App\Filament\Resources\Schedules\Schemas\ScheduleForm::slotLabel($slot))
                                ->implode(''))
                            ->html()
                            ->placeholder(__('No slots yet.')),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
