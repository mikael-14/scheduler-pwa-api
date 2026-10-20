<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Services\PrimaryScheduleRule;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
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

    /**
     * "View" opens the schedule in a modal where it can be approved or rejected with a message.
     * The message is appended to the schedule's internal note (see Schedule::approve/reject).
     */
    private function reviewAction(): Action
    {
        return Action::make('review')
            ->label(__('View'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->iconButton()
            ->modalHeading(fn(Schedule $record) => __('Schedule of :name', ['name' => $record->user?->name ?? '-']))
            ->modalWidth('2xl')
            ->schema([
                Grid::make(2)->schema([
                    TextEntry::make('type')
                        ->label(__('Type'))
                        ->state(fn(Schedule $record) => $record->schedule_type?->name),
                    TextEntry::make('owner')
                        ->label(__('Assigned To'))
                        ->state(fn(Schedule $record) => $record->user?->name)
                        ->placeholder('-'),
                    TextEntry::make('period_start')
                        ->label(__('Start'))
                        ->state(fn(Schedule $record) => $record->start?->format($record->all_day
                            ? config('app.date_format')
                            : config('app.date_time_format')))
                        ->helperText(fn(Schedule $record) => $record->all_day ? __('All Day') : null),
                    TextEntry::make('period_end')
                        ->label(__('End'))
                        ->state(fn(Schedule $record) => $record->end?->format(config('app.date_time_format')))
                        ->placeholder('-'),
                    TextEntry::make('people')
                        ->label(__('Participants'))
                        ->state(fn(Schedule $record) => $record->schedule_users->pluck('user.name')->filter()->all())
                        ->badge()
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('rule')
                        ->label(__('Cannot be approved yet'))
                        ->state(fn(Schedule $record) => PrimaryScheduleRule::scheduleProblem($record))
                        ->visible(fn(Schedule $record) => PrimaryScheduleRule::scheduleProblem($record) !== null)
                        ->color('warning')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->columnSpanFull(),
                    TextEntry::make('details')
                        ->label(__('Description'))
                        ->state(fn(Schedule $record) => $record->description)
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('history')
                        ->label(__('Internal Note'))
                        ->state(fn(Schedule $record) => $record->internal_note)
                        ->visible(fn(Schedule $record) => filled($record->internal_note))
                        ->extraAttributes(['class' => 'whitespace-pre-line'])
                        ->columnSpanFull(),
                ]),
                Textarea::make('message')
                    ->label(__('Message'))
                    ->helperText(__('Added to the internal note of the schedule.'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel(__('Approve'))
            ->modalSubmitAction(fn(Action $action) => $action->color('success')->icon('heroicon-o-check'))
            ->extraModalFooterActions(fn(Action $action) => [
                $action->makeModalSubmitAction('reject', ['decision' => 'reject'])
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger'),
            ])
            ->action(function (Schedule $record, array $data, array $arguments) {
                if ($record->status !== ScheduleStatus::Pending) {
                    Notification::make()->title(__('This schedule is no longer pending.'))->warning()->send();

                    return;
                }

                if (($arguments['decision'] ?? 'approve') === 'reject') {
                    $record->reject($data['message'] ?? null);
                    Notification::make()->title(__('Schedule rejected'))->success()->send();
                } else {
                    $record->approve($data['message'] ?? null);
                    Notification::make()->title(__('Records approved successfully'))->success()->send();
                }
            });
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
                    // primary type: nobody who may go alone is approved at these hours yet
                    ->description(fn(Schedule $record) => PrimaryScheduleRule::scheduleProblem($record) ? __('Cannot be approved yet') : null)
                    ->icon(fn(Schedule $record) => PrimaryScheduleRule::scheduleProblem($record) ? 'heroicon-m-exclamation-triangle' : null)
                    ->iconColor('warning')
                    ->tooltip(fn(Schedule $record) => PrimaryScheduleRule::scheduleProblem($record))
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
                $this->reviewAction(),
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
