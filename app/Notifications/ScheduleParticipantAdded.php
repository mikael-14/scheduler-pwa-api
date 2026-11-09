<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * Sent to someone added as a participant of a schedule (when it is created, or later, or put back
 * after cancelling), so they know they are in it, with a reminder not to arrive early.
 */
class ScheduleParticipantAdded extends AppNotification
{
    /** Who added them, kept for the queued email (sent after the request is over). */
    private readonly ?string $by;

    public function __construct(private readonly Schedule $schedule)
    {
        $this->by = auth()->user()?->name;
    }

    protected function title(): string
    {
        return __('You were added to a schedule');
    }

    /** In the app and on the phone: the same sentences as the email. */
    protected function body(): string
    {
        return implode(' ', $this->mailLines());
    }

    /** By email, in full sentences: who added them, to what and when, and the reminder. */
    protected function mailLines(): array
    {
        $what = [
            'type' => $this->schedule->schedule_type?->name ?? __('Schedule'),
            'date' => $this->schedule->start?->format(config('app.date_time_format')),
            'by' => $this->by ?? $this->schedule->user?->name,
        ];

        return [
            $what['by']
                ? __(':by added you to the :type schedule on :date.', $what)
                : __('You were added to the :type schedule on :date.', $what),
            __('Please do not arrive before the scheduled time.'),
        ];
    }

    protected function mailActionLabel(): string
    {
        return __('Open the schedule');
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }
}
