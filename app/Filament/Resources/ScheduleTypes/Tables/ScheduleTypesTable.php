<?php

namespace App\Filament\Resources\ScheduleTypes\Tables;

use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Collection;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('name')
                    ->searchable(),
                ColorColumn::make('color'),
                TextColumn::make('color_code')->state(function (Model $record) {
                    return $record->color ?? 'N/A';
                })->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('schedules_count')->counts('schedules'),
                IconColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
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
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                TernaryFilter::make('status'),
                TernaryFilter::make('range'),
                TernaryFilter::make('all_day'),
                Filter::make('description')
                    ->schema([
                        TextInput::make('description')
                            ->label('Description')
                            ->placeholder('Search...')
                            ->live()
                            ->debounce(500),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['description'], function (Builder $query, $value) {
                            return $query->where('description', 'like', "%{$value}%");
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
