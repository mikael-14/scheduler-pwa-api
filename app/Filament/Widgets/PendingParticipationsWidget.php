<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleUser;
use App\Services\PrimaryScheduleRule;
use App\Services\ScheduleParticipation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * People who joined an approved schedule and wait for their own decision (participations on a
 * pending schedule are decided with it, in PendingSchedulesWidget).
 */
class PendingParticipationsWidget extends TableWidget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PendingSchedulesWidget::canView();
    }

    public static function pendingQuery(): Builder
    {
        return ScheduleUser::query()
            ->where('status', ScheduleStatus::Pending)
            ->whereHas('schedule', fn(Builder $query) => $query->where('status', ScheduleStatus::Approved));
    }

    private function decision(string $name, ScheduleStatus $decision): Action
    {
        $approve = $decision === ScheduleStatus::Approved;

        return Action::make($name)
            ->label($approve ? __('Approve') : __('Reject'))
            ->icon($approve ? 'heroicon-o-check' : 'heroicon-o-x-circle')
            ->color($approve ? 'success' : 'danger')
            ->iconButton()
            ->modalDescription(fn(ScheduleUser $record) => $approve ? PrimaryScheduleRule::participantProblem($record) : null)
            ->schema([
                Textarea::make('message')
                    ->label(__('Message'))
                    ->helperText(__('Sent to the person with the decision.'))
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (ScheduleUser $record, array $data) use ($decision, $approve) {
                if ($record->status !== ScheduleStatus::Pending) {
                    Notification::make()->title(__('This participation is no longer pending.'))->warning()->send();

                    return;
                }

                ScheduleParticipation::decide($record, $decision, $data['message'] ?? null);

                Notification::make()
                    ->title($approve ? __('Participation approved') : __('Participation rejected'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Participations awaiting approval'))
            ->query(fn(): Builder => static::pendingQuery()->with(['user', 'schedule.user', 'schedule.schedule_type']))
            ->columns([
                ColorColumn::make('schedule.schedule_type.color')
                    ->label(''),
                TextColumn::make('user.name')
                    ->label(__('Participant'))
                    ->description(fn(ScheduleUser $record) => PrimaryScheduleRule::participantProblem($record) ? __('Cannot be approved yet') : null)
                    ->icon(fn(ScheduleUser $record) => PrimaryScheduleRule::participantProblem($record) ? 'heroicon-m-exclamation-triangle' : null)
                    ->iconColor('warning')
                    ->searchable(),
                TextColumn::make('schedule.schedule_type.name')
                    ->label(__('Type')),
                TextColumn::make('schedule.user.name')
                    ->label(__('Assigned To'))
                    ->placeholder('-'),
                TextColumn::make('schedule.start')
                    ->label(__('Start'))
                    ->dateTime(fn(ScheduleUser $record) => $record->schedule?->all_day
                        ? config('app.date_format')
                        : config('app.date_time_format')),
                TextColumn::make('schedule.end')
                    ->label(__('End'))
                    ->dateTime(config('app.date_time_format'))
                    ->placeholder('-'),
            ])
            ->recordActions([
                $this->decision('approve', ScheduleStatus::Approved),
                $this->decision('reject', ScheduleStatus::Rejected),
            ])
            ->emptyStateHeading(__('No participations awaiting approval'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
