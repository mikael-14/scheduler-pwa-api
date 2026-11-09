<?php

namespace App\Filament\Resources\Schedules\RelationManagers;

use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ViewSchedule;
use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Services\ScheduleAccess;
use App\Services\ScheduleParticipation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The people who cancelled their participation (or were taken off). They stay on the schedule,
 * marked cancelled, and are never counted. Whoever edits other people's schedules
 * (`update_any_schedule`) may put one back on the schedule or remove one for good.
 */
class CancelledParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'cancelled_schedule_users';

    /** On the View and Edit pages, when someone cancelled. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return in_array($pageClass, [ViewSchedule::class, EditSchedule::class], true)
            && $ownerRecord instanceof Schedule
            && $ownerRecord->cancelled_schedule_users()->exists();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Cancelled participants');
    }

    /** The "delete for good" action also works on the View page. */
    public function isReadOnly(): bool
    {
        return false;
    }

    /** Restore / delete for good: whoever edits other people's schedules (and this one). */
    public static function mayManageCancelled(Schedule $schedule): bool
    {
        $user = Filament::auth()->user();

        return $user && $user->can('update_any_schedule') && ScheduleAccess::canUpdate($user, $schedule);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with('user'))
            ->defaultSort('cancelled_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('cancelled_at')
                    ->label(__('Cancelled at'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable(),
            ])
            ->recordActions([
                // back on the schedule like joining again: the schedule's status now, the primary
                // rule checked (a refusal is shown and nothing changes), the person is told
                Action::make('restoreParticipant')
                    ->label(__('Restore'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Restore participant'))
                    ->modalDescription(__('The person is back on the schedule with its current status, and is told.'))
                    ->visible(fn() => self::mayManageCancelled($this->getOwnerRecord()))
                    ->action(function (ScheduleUser $record) {
                        abort_unless(self::mayManageCancelled($this->getOwnerRecord()), 403);
                        ScheduleParticipation::restore($record);
                        Notification::make()->title(__('Participant restored'))->success()->send();
                    }),
                Action::make('removeForGood')
                    ->label(__('Delete for good'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Delete for good'))
                    ->modalDescription(__('The person is removed from this schedule completely. The history keeps a record of it.'))
                    ->visible(fn() => self::mayManageCancelled($this->getOwnerRecord()))
                    ->action(function (ScheduleUser $record) {
                        abort_unless(self::mayManageCancelled($this->getOwnerRecord()), 403);
                        ScheduleParticipation::removeForGood($record);
                        Notification::make()->title(__('Removed for good'))->success()->send();
                    }),
            ]);
    }
}
