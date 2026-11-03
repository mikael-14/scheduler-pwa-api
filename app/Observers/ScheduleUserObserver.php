<?php

namespace App\Observers;

use App\Enums\ScheduleStatus;
use App\Models\ScheduleUser;
use App\Notifications\MarkedNotShown;
use App\Notifications\NotShownRecorded;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\ScheduleParticipantAdded;
use App\Notifications\ScheduleJoinedOrLeft;
use App\Notifications\ScheduleParticipantRemoved;
use App\Services\ScheduleActivityFeed;

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
        $this->tellAround($participant, joined: true);
        $this->tellEveryone($participant, joined: true);
    }

    /**
     * Marked as not shown by the owner or an admin (API or admin panel): the person is told, and
     * so are the approvers.
     */
    public function updated(ScheduleUser $participant): void
    {
        if (! $participant->wasChanged('status') || $participant->status !== ScheduleStatus::NotShown || ! auth()->check()) {
            return;
        }

        $this->tell($participant, fn($schedule) => new MarkedNotShown($schedule));

        $schedule = $participant->schedule()->with(['schedule_type', 'user'])->first();
        if ($schedule) {
            NotShownRecorded::tellApprovers($schedule, $participant->user);
        }
    }

    public function deleted(ScheduleUser $participant): void
    {
        $this->tell($participant, fn($schedule) => new ScheduleParticipantRemoved($schedule));
        $this->tellAround($participant, joined: false);
        $this->tellEveryone($participant, joined: false);
    }

    /**
     * Someone joined or was added, left or was removed: the owner, the other people on the
     * schedule and the approvers are told (never whoever did it, nor the person, who gets
     * tell() when it was done to them). Not for the people booked together with a new schedule:
     * that is the booking itself.
     */
    private function tellAround(ScheduleUser $participant, bool $joined): void
    {
        $user = $participant->user;
        $schedule = $participant->schedule()->with(['schedule_type', 'user', 'schedule_users.user'])->first();

        // handed the schedule itself (becomes its owner), or the schedule is gone
        if (! $user || ! $schedule || $schedule->user_id === $user->id) {
            return;
        }

        if ($joined && ScheduleActivityFeed::instance()->bookedInThisRequest($schedule)) {
            return;
        }

        $byOthers = auth()->id() !== $user->id;

        $recipients = collect([$schedule->user])
            ->merge($schedule->schedule_users->pluck('user'))
            ->merge(ScheduleObserver::approvers())
            ->filter(fn(?User $person) => $person
                && $person->id !== $user->id
                && $person->id !== auth()->id()
                && $person->status
                && $person->approved_at)
            ->unique('id');

        foreach ($recipients as $person) {
            try {
                $person->notify(new ScheduleJoinedOrLeft($schedule, $user, $joined, yours: $person->id === $schedule->user_id, byOthers: $byOthers));
            } catch (\Throwable $e) {
                // a mail server hiccup must never undo or block saving the schedule
                report($e);
            }
        }
    }

    /** "All notifications": someone joined or left (not when they became the owner instead). */
    private function tellEveryone(ScheduleUser $participant, bool $joined): void
    {
        $schedule = $participant->schedule()->first();
        $user = $participant->user;

        if (! $schedule || ! $user || $schedule->user_id === $user->id) {
            return;
        }

        ScheduleActivityFeed::instance()->participants($schedule, $user, $joined);
    }

    /** @param callable(\App\Models\Schedule): AppNotification $notification */
    private function tell(ScheduleUser $participant, callable $notification): void
    {
        $user = $participant->user;

        if (! static::shouldTell($user)) {
            return;
        }

        $schedule = $participant->schedule()->withTrashed()->with(['schedule_type', 'user'])->first();
        // handed the schedule itself: "booked for you" tells them, not "removed"
        if (! $schedule || $schedule->user_id === $user->id) {
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
