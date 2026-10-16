<?php

namespace App\Observers;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\SchedulePendingApproval;
use App\Notifications\ScheduleStatusChanged;
use Spatie\Permission\Models\Permission;

/**
 * One place that tells people about a schedule decision, whatever triggered it:
 * the API, the table or page actions of the admin panel, a widget or a bulk action.
 */
class ScheduleObserver
{
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
    }

    public function updated(Schedule $schedule): void
    {
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
