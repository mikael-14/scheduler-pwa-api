<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Services\PrimaryScheduleRule;
use App\Services\ScheduleAccess;
use App\Services\ScheduleParticipation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSchedule extends ViewRecord
{
    protected static string $resource = ScheduleResource::class;

    /** The page sends the record's columns to the browser: the internal note only to staff. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! ScheduleAccess::seesInternalNote(Filament::auth()->user())) {
            unset($data['internal_note']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $canDecide = fn(Schedule $record): bool => $record->status === ScheduleStatus::Pending
            && ScheduleAccess::canApprove(Filament::auth()->user());

        return [
            // Schedule::approve / reject: participants follow, and the primary rule is checked
            Action::make('approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->modalDescription(fn(Schedule $record) => PrimaryScheduleRule::scheduleProblem($record))
                ->color('success')
                ->action(function (Schedule $record) {
                    $record->approve();
                    Notification::make()->title(__('Records approved successfully'))->success()->send();
                })
                ->visible($canDecide),
            Action::make('reject')
                ->label(__('Reject'))
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->action(function (Schedule $record) {
                    $record->reject();
                    Notification::make()->title(__('Schedule rejected'))->success()->send();
                })
                ->visible($canDecide),
            Action::make('join')
                // after cancelling: joining again (before it starts)
                ->label(fn(Schedule $record) => ScheduleAccess::cancelledParticipation(Filament::auth()->user(), $record)
                    ? __('Join again')
                    : __('Join'))
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('You are going to take part in this schedule. Please do not arrive before the scheduled time.'))
                ->action(function (Schedule $record) {
                    ScheduleParticipation::join(Filament::auth()->user(), $record);
                    $record->load(['schedule_users', 'cancelled_schedule_users']);
                    Notification::make()->title(__('You joined this schedule'))
                        ->body(__('Please do not arrive before the scheduled time.'))
                        ->success()->send();
                })
                ->visible(fn(Schedule $record) => ScheduleAccess::canJoin(Filament::auth()->user(), $record)),
            // cancels the participation: they stay on the schedule as cancelled and can join again
            Action::make('leave')
                ->label(__('Cancel attendance'))
                ->icon('heroicon-o-user-minus')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('You are going to cancel your attendance. If you change your mind, you can join again until it starts.'))
                ->action(function (Schedule $record) {
                    ScheduleParticipation::leave(Filament::auth()->user(), $record);
                    $record->load(['schedule_users', 'cancelled_schedule_users']);
                    Notification::make()->title(__('Your attendance was cancelled'))
                        ->body(__('You can join again until it starts.'))
                        ->success()->send();
                })
                ->visible(fn(Schedule $record) => ScheduleAccess::canLeave(Filament::auth()->user(), $record)),
            EditAction::make(),
        ];
    }
}
