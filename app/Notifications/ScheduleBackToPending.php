<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to the people of an approved primary schedule that went back to pending because nobody who
 * may go alone is approved at those hours any more (someone cancelled, left or was moved).
 */
class ScheduleBackToPending extends AppNotification
{
    public function __construct(private readonly Schedule $schedule)
    {
    }

    protected function title(): string
    {
        return __('Your schedule is waiting for approval again');
    }

    protected function body(): string
    {
        $line = implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
        ]));

        return $line . "\n" . __('Nobody who may go alone is approved at these hours any more.');
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
