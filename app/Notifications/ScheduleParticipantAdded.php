<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to someone added as a participant of a schedule (when it is created, or later, or put back
 * after cancelling), so they know they are in it, with a reminder not to arrive early.
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
        // a practical reminder, not the approval rules (as the app says when joining)
        return implode(' · ', array_filter($parts)) . "\n" . __('Please do not arrive before the scheduled time.');
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }
}
