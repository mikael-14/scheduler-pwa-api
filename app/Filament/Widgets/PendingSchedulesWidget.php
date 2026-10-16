<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingSchedulesWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user?->can('approve_schedule') || $user?->can('update_status_schedule'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Schedules awaiting approval'))
            ->query(fn(): Builder => Schedule::query()
                ->with(['user', 'schedule_type', 'schedule_users.user'])
                ->where('status', ScheduleStatus::Pending)
                ->orderBy('start'))
            ->defaultSort('start')
            ->columns([
                ColorColumn::make('schedule_type.color')
                    ->label(''),
                TextColumn::make('schedule_type.name')
                    ->label(__('Type')),
                TextColumn::make('user.name')
                    ->label(__('Assigned To'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('schedule_users.user.name')
                    ->label(__('Participants'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('start')
                    ->label(__('Start'))
                    ->dateTime(fn(Schedule $record) => $record->all_day
                        ? config('app.date_format')
                        : config('app.date_time_format'))
                    ->description(fn(Schedule $record) => $record->all_day ? __('All Day') : null)
                    ->sortable(),
                TextColumn::make('end')
                    ->label(__('End'))
                    ->dateTime(config('app.date_time_format'))
                    ->placeholder('-'),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->limit(40)
                    ->tooltip(fn(Schedule $record) => $record->description)
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->action(function (Schedule $record) {
                        $record->approve();

                        Notification::make()->title(__('Records approved successfully'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->action(function (Schedule $record) {
                        $record->reject();

                        Notification::make()->title(__('Schedule rejected'))->success()->send();
                    }),
            ])
            ->emptyStateHeading(__('No schedules awaiting approval'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
