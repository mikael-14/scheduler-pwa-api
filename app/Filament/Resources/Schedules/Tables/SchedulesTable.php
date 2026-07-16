<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Schemas\ScheduleForm;
use App\Models\Schedule;
use App\Models\ScheduleType;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Dom\Text;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
use Illuminate\Database\Eloquent\Collection;
use Zvizvi\UserFields\Components\UserColumn;
use Zvizvi\UserFields\Components\UserSelectFilter;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColumnGroup::make('Schedule Type')
                    ->columns([
                        ColorColumn::make('schedule_type.color')
                            ->label(__('Color')),
                        TextColumn::make('schedule_type.name')
                            ->label(__('Type'))
                            ->searchable()
                            ->sortable(),
                    ])
                    ->alignLeft()
                    ->wrapHeader(),
                UserColumn::make('user')
                    ->label(__('Assigned To'))
                    ->wrapped(false)
                    ->tooltip(fn($record) => $record->description)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('start')
                    ->label(__('Start'))
                    ->date(fn($record) => $record->all_day ? config('app.date_format') : config('app.date_time_format'))
                    ->description(fn($record) => $record->all_day ? 'All Day' : null)
                    ->sortable(),
                TextColumn::make('end')
                    ->label(__('End'))
                    ->date(config('app.date_time_format'))
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('all_day')
                    ->boolean()
                    ->label(__('All Day'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('schedule_users.user.name')
                    ->label(__('Participants'))
                    ->badge()
                    ->color(function (string $state, $record) {
                        // Find the relation item matching the current badge's user name
                        $scheduleUser = $record->schedule_users
                            ->first(fn($su) => $su->user?->name === $state);

                        // Return the color directly from your ScheduleStatus Enum
                        return $scheduleUser?->status?->getColor() ?? 'gray';
                    })
                    ->icon(function (string $state, $record) {
                        // Find the relation item matching the current badge's user name
                        $scheduleUser = $record->schedule_users
                            ->first(fn($su) => $su->user?->name === $state);

                        // Return the icon directly from your ScheduleStatus Enum
                        return $scheduleUser?->status?->getIcon();
                    })
                    ->placeholder('-')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('internal_note')
                    ->label(__('Internal Note'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label(__('Deleted At'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['schedule_users.user']))
            ->filters([
                SelectFilter::make('schedule_type')
                    ->relationship('schedule_type', 'name')
                    ->preload()
                    ->searchable()
                    ->multiple()
                    ->columnSpan(2),
                Filter::make('date_range')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('from')
                                    ->label(__('From'))
                                    ->native(false)
                                    ->hoursStep(1) // Intervals of incrementing hours in a time picker
                                    ->minutesStep(5) // Intervals of minute increment in a time picker
                                    ->seconds(false) // Enable seconds in a time picker
                                    ->displayFormat(config('app.date_time_format')),
                                DateTimePicker::make('end')
                                    ->label(__('End'))
                                    ->native(false)
                                    ->hoursStep(1) // Intervals of incrementing hours in a time picker
                                    ->minutesStep(5) // Intervals of minute increment in a time picker
                                    ->seconds(false) // Enable seconds in a time picker
                                    ->displayFormat(config('app.date_time_format')),
                            ]),
                    ])
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
                    ->columnSpan(2),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ScheduleStatus::class),
                UserSelectFilter::make('assigned_to')
                    ->label(__('Assigned To'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->columnSpan(3),
                TernaryFilter::make('all_day')
                    ->label(__('All Day')),
                TrashedFilter::make(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function (Schedule $record) {
                        $record->update([
                            'status' => ScheduleStatus::Approved,
                        ]);
                        $record->participants()->each(function ($participant) {
                            if ($participant->status === ScheduleStatus::Pending) {
                                $participant->update([
                                    'status' => ScheduleStatus::Approved,
                                ]);
                            }
                        });
                    })
                    ->visible(
                        fn(Schedule $record): bool => ($record->status === ScheduleStatus::Pending && Filament::auth()->user()->can(('approve_schedule'))) ||
                            (Filament::auth()->user()->can(('update_status_schedule')))
                    ),
                EditAction::make()->iconButton(),
            ])
            ->columnManagerColumns(2)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    BulkAction::make('approve')
                        ->label(__('Approve selected'))
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                if ($record->status === ScheduleStatus::Pending) {
                                    $record->update([
                                        'status' => ScheduleStatus::Approved,
                                    ]);
                                    $record->participants()->each(function ($participant) {
                                        if ($participant->status === ScheduleStatus::Pending) {
                                            $participant->update([
                                                'status' => ScheduleStatus::Approved,
                                            ]);
                                        }
                                    });
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(__('Records approved successfully'))
                        ->visible(
                            fn(): bool => (Filament::auth()->user()->can(('approve_schedule'))) ||
                                (Filament::auth()->user()->can(('update_status_schedule')))
                        ),
                ]),
            ]);
    }
}
