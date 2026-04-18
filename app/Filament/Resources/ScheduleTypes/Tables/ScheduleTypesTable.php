<?php

namespace App\Filament\Resources\ScheduleTypes\Tables;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use Dom\Text;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Collection;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\Ternary;

class ScheduleTypesTable
{

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('color_code')->state(function (Model $record) {
                    return $record->color ?? 'N/A';
                })->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('schedules_count')
                    ->counts([
                        'schedules' => fn(Builder $query) => $query->where('status', ScheduleStatus::Pending),
                    ]),
                IconColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('start')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('range')
                    ->label('Range')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('all_day')
                    ->label('All Day')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Row 1: Quick Toggle Filters (4 Columns)
                TernaryFilter::make('status')
                    ->label('Status'),
                TernaryFilter::make('range')
                    ->label('Range'),
                TernaryFilter::make('all_day')
                    ->label('All Day'),
                Filter::make('description')
                    ->schema([
                        TextInput::make('description')
                            ->label('Description')->columnSpanFull()
                    ])->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['description'], fn($q, $v) => $q->where('description', 'like', "%{$v}%"));
                    }),
                Filter::make('range_date')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start')
                                    ->label('Start')
                                    ->native(false)
                                    ->displayFormat(config('app.date_format')),
                                DatePicker::make('end')
                                    ->label('End')
                                    ->native(false)
                                    ->displayFormat(config('app.date_format')),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['start'], fn($q, $date) => $q->whereDate('start', '>=', $date))
                            ->when($data['end'], fn($q, $date) => $q->whereDate('end', '<=', $date));
                    })->columnSpanFull(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make()
                        ->before(function (DeleteAction $action) {
                            $record = $action->getRecord();
                            if ($record->schedules()->count() > 0) {
                                Notification::make()
                                    ->title('Cannot delete record')
                                    ->body('This record has related schedules and cannot be deleted.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $recordsWithoutSchedules = $records->filter(function (Model $record) {
                                return $record->schedules()->count() == 0;
                            });
                            if ($recordsWithoutSchedules->count() != $records->count()) {
                                Notification::make()
                                    ->title('Cannot delete some records')
                                    ->body('One or more selected records have related schedules and cannot be deleted.')
                                    ->danger()
                                    ->send();
                            }
                            $recordsWithoutSchedules->each->delete();
                        })
                        ->successNotificationTitle('Deleted')
                        ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                            if ($successCount) {
                                return "{$successCount} of {$totalCount} records deleted";
                            }

                            return 'Failed to delete any records';
                        }),
                ]),
            ]);
    }
}
