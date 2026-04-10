<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use BladeUI\Icons\Components\Icon;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ScheduleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn(Schedule $record) => "#({$record->id}) - {$record->schedule_type->name}")
                    ->schema([
                        TextEntry::make('schedule_type.name')
                            ->label('Schedule Type')
                            ->view('filament.components.select-with-color')
                            // We use a closure here to capture the record, then return the data array
                            ->viewData(function ($record) {
                                return [
                                    'name' => $record->schedule_type?->name ?? '-',
                                    'color' => $record->schedule_type?->color ?? '#eee',
                                    'label' => 'Schedule Type',
                                ];
                            }),
                          TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => ScheduleStatus::from($state)->getColor()),
                        TextEntry::make('user.name')
                            ->placeholder('-'),
                        TextEntry::make('start')
                            ->dateTime(fn ($record) => $record->all_day ? config('app.date_format') : config('app.date_time_format')) 
                            ->placeholder('-'),
                        IconEntry::make('all_day')
                            ->label('All Day')
                            ->visible(fn(Schedule $record) => $record->all_day)
                            ->boolean(),
                        TextEntry::make('end')
                            ->dateTime(config('app.date_time_format')) 
                            ->visible(fn(Schedule $record) => $record->end !== null)
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->placeholder('-'),
                        TextEntry::make('internal_note')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime(config('app.date_time_format'))
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime(config('app.date_time_format'))
                            ->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->dateTime(config('app.date_time_format'))
                            ->visible(fn(Schedule $record): bool => $record->trashed()),
                    ])->columnSpan('full')
                    ->columns(2),
            ]);
    }
}
