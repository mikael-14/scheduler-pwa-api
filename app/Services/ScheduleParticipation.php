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
 * - Leaving removes them (audited); an approved schedule that relied on them goes back to pending.
 * - The owner, the other people on it and the approvers are told (ScheduleUserObserver).
 */
class ScheduleParticipation
{
    public static function join(User $user, Schedule $schedule): ScheduleUser
    {
        return DB::transaction(fn() => $schedule->schedule_users()->create(['user_id' => $user->id]));
    }

    public static function leave(User $user, Schedule $schedule): void
    {
        $schedule->schedule_users()->where('user_id', $user->id)->first()?->delete();
    }
}
