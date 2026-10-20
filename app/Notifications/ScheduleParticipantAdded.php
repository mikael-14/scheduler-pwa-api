<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to someone added as a participant of a schedule (when it is created, or later), so they
 * know they are in it and whether it still waits for approval.
 */
class ScheduleParticipantAdded extends AppNotification
{
    public function __construct(private readonly Schedule $schedule)
    {
    }

    protected function title(): string
    {
        return __('You were added to a schedule');
    }

    protected function body(): string
    {
        $parts = [
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
            $this->schedule->user ? __('by :name', ['name' => $this->schedule->user->name]) : null,
        ];
        $line = implode(' · ', array_filter($parts));

        return $this->schedule->status?->value === 'pending'
            ? $line . "\n" . __('It is waiting for approval.')
            : $line;
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }
}
