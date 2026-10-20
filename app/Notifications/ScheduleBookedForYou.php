<?php

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\User;

/**
 * Sent to the owner of a schedule that someone else booked for them (create_any_schedule), or
 * handed over to them (update_any_schedule).
 */
class ScheduleBookedForYou extends AppNotification
{
    /** Who booked it, kept now: the queued email is written later. */
    private readonly ?string $bookedBy;

    public function __construct(private readonly Schedule $schedule, ?User $actor = null)
    {
        $this->bookedBy = $actor?->name;
    }

    protected function title(): string
    {
        return __('A schedule was booked for you');
    }

    protected function body(): string
    {
        $parts = [
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
            $this->bookedBy ? __('by :name', ['name' => $this->bookedBy]) : null,
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
