<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Enums\ScheduleStatus;
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
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schedule_type.name')
                    ->formatStateUsing(function ($record) {
                        $color = $record->schedule_type?->color ?? 'gray';
                        $name = $record->schedule_type?->name ?? 'N/A';
                        return view('filament.components.colored-icon-column', compact('color', 'name'));
                    })
                    ->searchable()
                    ->sortable(),
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
                        Section::make('Filter by Dates')
                            ->description('Narrow down schedules by start or end dates')
                            ->compact()
                            ->columns(4) // One column for each date picker
                            ->schema([
                                DatePicker::make('from_start')
                                    ->label('Start From'),
                                DatePicker::make('until_start')
                                    ->label('Start Until'),
                                DatePicker::make('from_end')
                                    ->label('End From'),
                                DatePicker::make('until_end')
                                    ->label('End Until'),
                            ]),
                    ])
                    ->columnSpanFull() // Forces this to take its own row
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_start'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start', '>=', $date),
                            )
                            ->when(
                                $data['until_start'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start', '<=', $date),
                            )
                            ->when(
                                $data['from_end'],
                                fn(Builder $query, $date): Builder => $query->whereDate('end', '>=', $date),
                            )
                            ->when(
                                $data['until_end'],
                                fn(Builder $query, $date): Builder => $query->whereDate('end', '<=', $date),
                            );
                    }),
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
