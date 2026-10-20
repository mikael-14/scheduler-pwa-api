<?php

namespace App\Observers;

use App\Models\ScheduleUser;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\ScheduleParticipantAdded;
use App\Notifications\ScheduleParticipantRemoved;

/**
 * Tells people they were added to or removed from a schedule, whatever did it: the API (PWA),
 * the admin panel's participants list, or the schedule's creation itself.
 * Never for what someone did to themselves.
 */
class ScheduleUserObserver
{
    public function created(ScheduleUser $participant): void
    {
        $this->tell($participant, fn($schedule) => new ScheduleParticipantAdded($schedule));
    }

    public function deleted(ScheduleUser $participant): void
    {
        $this->tell($participant, fn($schedule) => new ScheduleParticipantRemoved($schedule));
    }

    /** @param callable(\App\Models\Schedule): AppNotification $notification */
    private function tell(ScheduleUser $participant, callable $notification): void
    {
        $user = $participant->user;

        if (! static::shouldTell($user)) {
            return;
        }

        $schedule = $participant->schedule()->withTrashed()->with(['schedule_type', 'user'])->first();
        if (! $schedule) {
            return;
        }

        try {
            $user->notify($notification($schedule));
        } catch (\Throwable $e) {
            // a mail server hiccup must never undo or block saving the schedule
            report($e);
        }
    }

    /**
     * A signed-in person (not a seeder, import or console task) did it to someone else, and that
     * account can use the app (active and approved).
     */
    public static function shouldTell(?User $user): bool
    {
        return $user
            && auth()->check()
            && $user->id !== auth()->id()
            && $user->status
            && $user->approved_at;
    }
}
