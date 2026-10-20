<?php

namespace App\Notifications;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Models\User;

/** Sent to approvers when someone joins a schedule: their participation waits for a decision. */
class ParticipationRequested extends AppNotification
{
    public function __construct(private readonly Schedule $schedule, private readonly User $participant)
    {
    }

    protected function title(): string
    {
        return __(':name wants to join a schedule', ['name' => $this->participant->name]);
    }

    protected function body(): string
    {
        $parts = [
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
            $this->schedule->user ? __('by :name', ['name' => $this->schedule->user->name]) : null,
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

    protected function url(): ?string
    {
        return ScheduleResource::getUrl('infolist', ['record' => $this->schedule], panel: 'admin');
    }
}
