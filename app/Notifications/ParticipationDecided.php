<?php

namespace App\Notifications;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;

/** Sent to someone who joined a schedule when their participation is approved or rejected. */
class ParticipationDecided extends AppNotification
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly ScheduleStatus $decision,
        private readonly ?string $message = null,
    ) {
    }

    protected function title(): string
    {
        return $this->decision === ScheduleStatus::Approved
            ? __('Your participation was approved')
            : __('Your participation was rejected');
    }

    protected function body(): string
    {
        $line = implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
        ]));

        return filled($this->message) ? $line . "\n" . trim($this->message) : $line;
    }

    protected function status(): string
    {
        return $this->decision === ScheduleStatus::Approved ? 'success' : 'danger';
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }
}
