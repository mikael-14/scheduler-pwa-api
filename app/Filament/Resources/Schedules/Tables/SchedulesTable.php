<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Enums\ScheduleStatus;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColumnGroup::make('Schedule Type')
                    ->columns([
                        ColorColumn::make('schedule_type.color')
                            ->label('Color'),
                        TextColumn::make('schedule_type.name')
                            ->label('Type')
                            ->searchable()
                            ->sortable(),
                    ])
                    ->alignLeft()
                    ->wrapHeader(),
                TextColumn::make('user.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('start')
                    ->date(fn($record) => $record->all_day ? config('app.date_format') : config('app.date_time_format'))
                    ->description(fn($record) => $record->all_day ? 'All Day' : null)
                    ->sortable(),
                TextColumn::make('end')
                    ->date(config('app.date_time_format'))
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('all_day')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => ScheduleStatus::from($state)->getColor()),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('internal_note')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Row 1: Main Status & Category Selectors
                SelectFilter::make('schedule_type')
                    ->relationship('schedule_type', 'name')
                    ->preload()
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(ScheduleStatus::class),
                TernaryFilter::make('all_day')
                    ->label('All day event?'),
                TrashedFilter::make(),
                // Row 2: Date Ranges in a clean, spans-all-columns section
                Filter::make('date_range')
                    ->schema([
                        Grid::make(2) // Create a 2-column grid for the dates
                            ->schema([
                                DateTimePicker::make('from')
                                    ->native(false)
                                    ->hoursStep(1) // Intervals of incrementing hours in a time picker
                                    ->minutesStep(5) // Intervals of minute increment in a time picker
                                    ->seconds(false) // Enable seconds in a time picker
                                    ->displayFormat(config('app.date_time_format')),
                                DateTimePicker::make('end')
                                    ->native(false)
                                    ->hoursStep(1) // Intervals of incrementing hours in a time picker
                                    ->minutesStep(5) // Intervals of minute increment in a time picker
                                    ->seconds(false) // Enable seconds in a time picker
                                    ->displayFormat(config('app.date_time_format')),
                            ])
                    ])
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start', '>=', $date),
                            )
                            ->when(
                                $data['end'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start', '<=', $date),
                            );
                    })
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
