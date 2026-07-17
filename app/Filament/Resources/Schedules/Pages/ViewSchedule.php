<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;

class ViewSchedule extends ViewRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->color('success')
                ->action(function (Schedule $record) {
                    $record->update([
                        'status' => ScheduleStatus::Approved,
                    ]);
                    $record->schedule_users()->each(function ($participant) {
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
            Action::make('reject')
                ->label(__('Reject'))
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->action(function (Schedule $record) {
                    $record->update([
                        'status' => ScheduleStatus::Rejected,
                    ]);
                    $record->schedule_users()->each(function ($participant) {
                        $participant->update([
                            'status' => ScheduleStatus::Rejected,
                        ]);
                    });
                })
                ->visible(
                    fn(Schedule $record): bool => ($record->status === ScheduleStatus::Pending && Filament::auth()->user()->can(('approve_schedule'))) ||
                        (Filament::auth()->user()->can(('update_status_schedule')))
                ),
            EditAction::make(),
        ];
    }
}
