<?php

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\User;

/** Sent to a schedule's owner when someone joins it or leaves it. */
class ScheduleJoinedOrLeft extends AppNotification
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly User $participant,
        public readonly bool $joined,
    ) {
    }

    protected function title(): string
    {
        return $this->joined
            ? __(':name joined your schedule', ['name' => $this->participant->name])
            : __(':name left your schedule', ['name' => $this->participant->name]);
    }

    protected function body(): string
    {
        return implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
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
