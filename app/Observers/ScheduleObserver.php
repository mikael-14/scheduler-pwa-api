<?php

namespace App\Observers;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleBookedForYou;
use App\Notifications\SchedulePendingApproval;
use App\Notifications\ScheduleStatusChanged;
use App\Notifications\ScheduleUpdated;
use Spatie\Permission\Models\Permission;

/**
 * One place that tells people about a schedule decision, whatever triggered it:
 * the API, the table or page actions of the admin panel, a widget or a bulk action.
 */
class ScheduleObserver
{
    /** Changes the owner and participants are told about. */
    private const WATCHED = ['start', 'end', 'all_day', 'schedule_type_id', 'description'];

    private const NOTIFIED = [
        ScheduleStatus::Approved,
        ScheduleStatus::Rejected,
        ScheduleStatus::Cancelled,
    ];

    /** A new request is waiting for a decision. */
    public function created(Schedule $schedule): void
    {
        if ($schedule->status === ScheduleStatus::Pending) {
            $this->notifyApprovers($schedule);
        }

        $this->notifyOwnerBookedBySomeoneElse($schedule);
    }

    public function updated(Schedule $schedule): void
    {
        // handed over to another owner (update_any_schedule)
        if ($schedule->wasChanged('user_id')) {
            $this->notifyOwnerBookedBySomeoneElse($schedule);
        }

        $this->notifyOfChanges($schedule);

        if (! $schedule->wasChanged('status')) {
            return;
        }

        // e.g. an approved schedule moved to another slot goes back to pending
        if ($schedule->status === ScheduleStatus::Pending) {
            $this->notifyApprovers($schedule);

            return;
        }

        if (! in_array($schedule->status, self::NOTIFIED, true)) {
            return;
        }

        $schedule->loadMissing(['user', 'schedule_type', 'schedule_users.user']);

        $actorId = auth()->id();

        collect([$schedule->user])
            ->merge($schedule->schedule_users->pluck('user'))
            ->filter()
            ->unique('id')
            // the person who made the change already knows
            ->reject(fn($user) => $user->id === $actorId)
            ->each(function ($user) use ($schedule) {
                try {
                    $user->notify(new ScheduleStatusChanged($schedule));
                } catch (\Throwable $e) {
                    // a mail server hiccup must never undo or block an approval
                    report($e);
                }
            });
    }

    /**
     * The owner and participants, when someone else changed the time, type or description.
     * Not for a save that also approves / rejects / cancels it: that decision is announced
     * already (ScheduleStatusChanged). Not for the internal note: staff only.
     */
    private function notifyOfChanges(Schedule $schedule): void
    {
        $changed = array_values(array_intersect(array_keys($schedule->getChanges()), self::WATCHED));

        if ($changed === [] || ($schedule->wasChanged('status') && in_array($schedule->status, self::NOTIFIED, true))) {
            return;
        }

        $schedule->loadMissing(['user', 'schedule_type', 'schedule_users.user']);
        $notification = new ScheduleUpdated($schedule, $changed);

        collect([$schedule->wasChanged('user_id') ? null : $schedule->user]) // a new owner was told already
            ->merge($schedule->schedule_users->pluck('user'))
            ->filter(fn($user) => ScheduleUserObserver::shouldTell($user))
            ->unique('id')
            ->each(function ($user) use ($notification) {
                try {
                    $user->notify($notification);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }

    /** The owner, when someone else booked the schedule for them (never for their own booking). */
    private function notifyOwnerBookedBySomeoneElse(Schedule $schedule): void
    {
        $schedule->loadMissing(['user', 'schedule_type']);
        $owner = $schedule->user;

        if (! ScheduleUserObserver::shouldTell($owner)) {
            return;
        }

        try {
            $owner->notify(new ScheduleBookedForYou($schedule, auth()->user()));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Everyone who may decide on schedules (approve_schedule or update_status_schedule, through a
     * role or directly), active and approved, except the person who made the request.
     */
    private function notifyApprovers(Schedule $schedule): void
    {
        $permissions = Permission::whereIn('name', ['approve_schedule', 'update_status_schedule'])->pluck('name')->all();

        if ($permissions === []) {
            return;
        }

        $schedule->loadMissing(['user', 'schedule_type']);

        User::permission($permissions)
            ->where('status', true)
            ->whereNotNull('approved_at')
            ->where('id', '!=', auth()->id() ?? 0)
            ->get()
            ->unique('id')
            ->each(function (User $approver) use ($schedule) {
                try {
                    $approver->notify(new SchedulePendingApproval($schedule));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
