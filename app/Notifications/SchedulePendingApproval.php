<?php

namespace App\Notifications;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;

/**
 * Sent to the people who can approve schedules when a request is waiting for a decision.
 */
class SchedulePendingApproval extends AppNotification
{
    public function __construct(private readonly Schedule $schedule)
    {
    }

    protected function title(): string
    {
        return __('New schedule awaiting approval');
    }

    protected function body(): string
    {
        $parts = [
            $this->schedule->user?->name,
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
        ];

        return implode(' · ', array_filter($parts));
    }

    protected function status(): string
    {
        return 'warning';
    }

    protected function path(): string
    {
        return '/approvals';
    }

    /** Approvers decide in the admin panel. */
    protected function url(): ?string
    {
        return ScheduleResource::getUrl('infolist', ['record' => $this->schedule], panel: 'admin');
    }
}
