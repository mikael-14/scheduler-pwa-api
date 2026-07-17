<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use BladeUI\Icons\Components\Icon;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
                            ->label(__('Schedule Type'))
                            ->view('filament.components.select-with-color')
                            // We use a closure here to capture the record, then return the data array
                            ->viewData(function ($record) {
                                return [
                                    'name' => $record->schedule_type?->name ?? '-',
                                    'color' => $record->schedule_type?->color ?? '#eee',
                                    'label' => __('Schedule Type'),
                                ];
                            }),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('user.name')
                            ->label(__('User'))
                            ->placeholder('-'),
                        TextEntry::make('start')
                            ->label(__('Start'))
                            ->dateTime(fn($record) => $record->all_day ? config('app.date_format') : config('app.date_time_format'))
                            ->placeholder('-'),
                        IconEntry::make('all_day')
                            ->label(__('All Day'))
                            ->visible(fn(Schedule $record) => $record->all_day)
                            ->boolean(),
                        TextEntry::make('end')
                            ->label(__('End'))
                            ->dateTime(config('app.date_time_format'))
                            ->visible(fn(Schedule $record) => $record->end !== null)
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-'),
                        TextEntry::make('internal_note')
                            ->label(__('Internal Note'))
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(config('app.date_time_format'))
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(config('app.date_time_format'))
                            ->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted At'))
                            ->dateTime(config('app.date_time_format'))
                            ->visible(fn(Schedule $record): bool => $record->trashed()),

                    ])->columnSpan('full')
                    ->columns(2),
                Section::make('Participants')
                    ->heading(__('Participants'))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('schedule_users')
                            ->label(__('Participants'))
                            ->table([
                                TableColumn::make('Name'),
                                TableColumn::make('Status'),
                                TableColumn::make('Description'),
                            ])
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label(__('Name'))
                                    ->placeholder('-'),
                                TextEntry::make('status')
                                    ->label(__('Status'))
                                    ->badge(),
                                TextEntry::make('description')
                                    ->label(__('Description'))
                                    ->placeholder('-'),
                            ])
                    ])->visible(fn(Schedule $record) => $record->schedule_users()->exists()),
            ]);
    }
}
