<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\User;
use App\Notifications\ScheduleActivity;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * "All notifications": tells everyone who turned on all_push_notification or
 * all_email_notification what happens on the agenda (booked, approved, changed, cancelled,
 * rejected, people joining or leaving), also for schedules they are not part of.
 *
 * Who: active, approved accounts that can open the agenda (`view_any_schedule`), except the
 * person who did it and the people already told personally (owner, participants, and for a new
 * booking the approvers).
 *
 * Called from ScheduleObserver and ScheduleUserObserver. Scoped to one request / job (see
 * AppServiceProvider): a schedule booked together with its participants is one notice, not one
 * per participant.
 */
class ScheduleActivityFeed
{
    /** @var array<int, int> schedule id => the request it was booked in (spl_object_id) */
    private array $bookedNow = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function booked(Schedule $schedule): void
    {
        $this->bookedNow[$schedule->id] = spl_object_id(request());

        $this->send($schedule, new ScheduleActivity($schedule, ScheduleActivity::CREATED), withApprovers: false);
    }

    /** approved / cancelled / rejected */
    public function decided(Schedule $schedule, string $event): void
    {
        $this->send($schedule, new ScheduleActivity($schedule, $event));
    }

    /** @param list<string> $fields what changed (start, end, all_day, schedule_type_id, description) */
    public function changed(Schedule $schedule, array $fields): void
    {
        $this->send($schedule, new ScheduleActivity(
            $schedule,
            ScheduleActivity::CHANGED,
            changes: \App\Notifications\ScheduleUpdated::captureChanges($schedule, $fields),
        ));
    }

    /** Someone joined or was added (joined: true), or left or was removed. */
    public function participants(Schedule $schedule, User $participant, bool $joined): void
    {
        // booked together with the schedule: the "booked" notice already says it
        if ($joined && ($this->bookedNow[$schedule->id] ?? null) === spl_object_id(request())) {
            return;
        }

        $this->send($schedule, new ScheduleActivity(
            $schedule,
            $joined ? ScheduleActivity::JOINED : ScheduleActivity::LEFT,
            participantName: $participant->name,
        ), alsoExclude: [$participant->id]);
    }

    /** @param list<int> $alsoExclude */
    private function send(Schedule $schedule, ScheduleActivity $notification, bool $withApprovers = true, array $alsoExclude = []): void
    {
        try {
            $recipients = $this->recipients($schedule, $withApprovers, $alsoExclude);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            }
        } catch (\Throwable $e) {
            // a mail server hiccup must never undo or block saving the schedule
            report($e);
        }
    }

    private function recipients(Schedule $schedule, bool $withApprovers, array $alsoExclude)
    {
        if (! Permission::where('name', 'view_any_schedule')->exists()) {
            return collect();
        }

        // told personally already (participants, also the ones being added with the booking)
        $exclude = collect([auth()->id(), $schedule->user_id, ...$alsoExclude, ...PrimaryScheduleRule::companionIds()])
            ->merge($schedule->schedule_users()->pluck('user_id'));

        if (! $withApprovers) {
            $approverPermissions = Permission::whereIn('name', ['approve_schedule', 'update_status_schedule'])->pluck('name')->all();
            if ($approverPermissions !== []) {
                $exclude = $exclude->merge(User::permission($approverPermissions)->pluck('id'));
            }
        }

        return User::permission('view_any_schedule')
            ->where('status', true)
            ->whereNotNull('approved_at')
            ->where(fn($query) => $query->where('all_push_notification', true)->orWhere('all_email_notification', true))
            ->whereNotIn('id', $exclude->filter()->unique()->values())
            ->get()
            ->unique('id')
            ->values();
    }
}
