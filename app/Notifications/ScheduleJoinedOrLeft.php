<?php

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\User;

/**
 * Someone joined a schedule or was added to it, or left it or was removed. Sent to its owner, the
 * other people on it and the approvers (ScheduleUserObserver), never to whoever did it.
 */
class ScheduleJoinedOrLeft extends AppNotification
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly User $participant,
        public readonly bool $joined,
        /** the recipient owns the schedule ("your schedule") */
        private readonly bool $yours = true,
        /** someone else did it (added / removed), not the person themselves (joined / left) */
        private readonly bool $byOthers = false,
    ) {
    }

    protected function title(): string
    {
        $name = ['name' => $this->participant->name];

        if ($this->yours) {
            return match (true) {
                $this->joined && $this->byOthers => __(':name was added to your schedule', $name),
                $this->joined => __(':name joined your schedule', $name),
                $this->byOthers => __(':name was removed from your schedule', $name),
                default => __(':name left your schedule', $name),
            };
        }

        return match (true) {
            $this->joined && $this->byOthers => __(':name was added to a schedule', $name),
            $this->joined => __(':name joined a schedule', $name),
            $this->byOthers => __(':name was removed from a schedule', $name),
            default => __(':name left a schedule', $name),
        };
    }

    protected function body(): string
    {
        return implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
            // for the people who do not own it: whose it is
            $this->yours ? null : $this->schedule->user?->name,
        ]));
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }

    /** Stored with the notification, so the app can tell joining from leaving. */
    public function toDatabase(object $notifiable): array
    {
        return parent::toDatabase($notifiable) + ['joined' => $this->joined];
    }
}
