<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to the person marked as not shown: a participant (marked by the schedule's owner or an
 * admin) or the owner of the schedule (marked by an admin), so they know, and can say so if it
 * is a mistake. The approvers get NotShownRecorded.
 */
class MarkedNotShown extends AppNotification
{
    public function __construct(private readonly Schedule $schedule)
    {
    }

    protected function title(): string
    {
        return __('You were marked as not shown');
    }

    protected function body(): string
    {
        return implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
            $this->schedule->user ? __('by :name', ['name' => $this->schedule->user->name]) : null,
        ]));
    }

    protected function status(): string
    {
        return 'warning';
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }
}
