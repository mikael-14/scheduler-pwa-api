<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Joining and leaving other people's schedules, for the API (PWA) and the admin panel. Who may do
 * it: ScheduleAccess::canJoin / canLeave.
 *
 * - Joining adds the person with the schedule's status: an approved schedule stays approved, and
 *   someone the primary rule does not allow there is refused (PrimaryScheduleRule).
 * - Leaving cancels the participation (a soft delete, ScheduleUser::cancelled_at): it stays on the
 *   schedule as cancelled, and an approved schedule that relied on them goes back to pending.
 * - Joining again after cancelling restores that same row, with the schedule's status at that moment.
 * - The owner, the other people on it and the approvers are told (ScheduleUserObserver).
 */
class ScheduleParticipation
{
    public static function join(User $user, Schedule $schedule): ScheduleUser
    {
        return DB::transaction(function () use ($user, $schedule) {
            $cancelled = $schedule->cancelled_schedule_users()->where('user_id', $user->id)->first();

            if ($cancelled) {
                $cancelled->restore();

                return $cancelled;
            }

            return $schedule->schedule_users()->create(['user_id' => $user->id]);
        });
    }

    public static function leave(User $user, Schedule $schedule): void
    {
        $schedule->schedule_users()->where('user_id', $user->id)->first()?->delete();
    }

    /** Admin panel: a cancelled participation put back, as if they joined again. */
    public static function restore(ScheduleUser $participant): void
    {
        DB::transaction(fn() => $participant->restore());
    }

    /** Admin panel: a cancelled participation removed from the schedule for good (audited). */
    public static function removeForGood(ScheduleUser $participant): void
    {
        $participant->forceDelete();
    }
}
