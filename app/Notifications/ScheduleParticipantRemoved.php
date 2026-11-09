<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to someone whose attendance was cancelled by someone else (the owner or an admin took them
 * off). They stay on the schedule as cancelled and may join again until it starts.
 */
class ScheduleParticipantRemoved extends AppNotification
{
    /** Who cancelled it, kept for the queued email (sent after the request is over). */
    private readonly ?string $by;

    public function __construct(private readonly Schedule $schedule)
    {
        $this->by = auth()->user()?->name;
    }

    protected function title(): string
    {
        return __('Your attendance was cancelled');
    }

    protected function body(): string
    {
        return implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
        ])) . "\n" . __('You can join again until it starts.');
    }

    protected function status(): string
    {
        return 'warning';
    }

    /** By email, in full sentences: what, when, who did it, and that they can join again. */
    protected function mailLines(): array
    {
        $what = [
            'type' => $this->schedule->schedule_type?->name ?? __('Schedule'),
            'date' => $this->schedule->start?->format(config('app.date_time_format')),
            'by' => $this->by,
        ];

        return [
            $this->by
                ? __('Your attendance at :type on :date was cancelled by :by.', $what)
                : __('Your attendance at :type on :date was cancelled.', $what),
            __('If you still want to go, you can join again in the app until it starts.'),
        ];
    }

    protected function mailActionLabel(): string
    {
        return __('Open the schedule');
    }

    /** Still on the schedule (as cancelled): open it, where they can join again. */
    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }
}
