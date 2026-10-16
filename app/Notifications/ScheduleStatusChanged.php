<?php

namespace App\Notifications;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;

/**
 * Sent to the owner and participants when a schedule is approved, rejected or cancelled.
 */
class ScheduleStatusChanged extends AppNotification
{
    public function __construct(private readonly Schedule $schedule)
    {
    }

    protected function title(): string
    {
        return match ($this->schedule->status) {
            ScheduleStatus::Approved => __('Your schedule was approved'),
            ScheduleStatus::Rejected => __('Your schedule was rejected'),
            ScheduleStatus::Cancelled => __('Your schedule was cancelled'),
            default => __('Your schedule status changed'),
        };
    }

    protected function body(): string
    {
        $type = $this->schedule->schedule_type?->name ?? __('Schedule');
        $start = $this->schedule->start?->format(config('app.date_time_format'));

        return trim($type . ' · ' . $start);
    }

    protected function status(): string
    {
        return match ($this->schedule->status) {
            ScheduleStatus::Approved => 'success',
            ScheduleStatus::Rejected, ScheduleStatus::Cancelled => 'danger',
            default => 'info',
        };
    }

    protected function path(): string
    {
        return '/my-agenda';
    }
}
