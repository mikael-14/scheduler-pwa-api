<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use App\Notifications\ParticipationDecided;
use App\Notifications\ParticipationRequested;
use App\Notifications\ScheduleJoinedOrLeft;
use App\Observers\ScheduleObserver;
use Illuminate\Support\Facades\DB;

/**
 * Joining and leaving other people's schedules, and deciding on those participations, for the
 * API (PWA) and the admin panel. Who may do it: ScheduleAccess::canJoin / canLeave / canApprove.
 *
 * - Joining adds a pending participation; approvers are told, and so is the owner.
 * - Leaving removes it (audited); the owner is told.
 * - Approving a participation obeys the primary rule (PrimaryScheduleRule, from the model hook).
 */
class ScheduleParticipation
{
    public static function join(User $user, Schedule $schedule): ScheduleUser
    {
        $participant = DB::transaction(fn() => $schedule->schedule_users()->updateOrCreate(
            ['user_id' => $user->id],
            ['status' => ScheduleStatus::Pending]
        ));

        $schedule->loadMissing(['user', 'schedule_type']);
        ScheduleObserver::tellApprovers(new ParticipationRequested($schedule, $user));
        static::tellOwner($schedule, $user, joined: true);

        return $participant;
    }

    public static function leave(User $user, Schedule $schedule): void
    {
        $participant = $schedule->schedule_users()->where('user_id', $user->id)->first();

        if (! $participant) {
            return;
        }

        $participant->delete();

        $schedule->loadMissing(['user', 'schedule_type']);
        static::tellOwner($schedule, $user, joined: false);
    }

    /** Approve or reject one participation. The participant is told. */
    public static function decide(ScheduleUser $participant, ScheduleStatus $decision, ?string $message = null): ScheduleUser
    {
        $participant->update(['status' => $decision]);

        $participant->loadMissing(['user', 'schedule.schedule_type', 'schedule.user']);
        $user = $participant->user;

        if ($user && $user->id !== auth()->id() && $user->status && $user->approved_at) {
            try {
                $user->notify(new ParticipationDecided($participant->schedule, $decision, $message));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $participant;
    }

    private static function tellOwner(Schedule $schedule, User $who, bool $joined): void
    {
        $owner = $schedule->user;

        if (! $owner || $owner->id === $who->id || ! $owner->status || ! $owner->approved_at) {
            return;
        }

        try {
            $owner->notify(new ScheduleJoinedOrLeft($schedule, $who, $joined));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
