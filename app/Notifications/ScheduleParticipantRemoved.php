<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to someone taken off a schedule's participants by someone else.
 */
class ScheduleParticipantRemoved extends AppNotification
{
    public function __construct(private readonly Schedule $schedule)
    {
    }

    protected function title(): string
    {
        return __('You were removed from a schedule');
    }

    protected function body(): string
    {
        return implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
        ]));
    }

    protected function status(): string
    {
        return 'warning';
    }

    /** No longer theirs: their own agenda, not the schedule. */
    protected function path(): string
    {
        return '/my-agenda';
    }
}
