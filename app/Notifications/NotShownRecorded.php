<?php

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\User;

/**
 * Sent to the approvers when someone is marked as not shown (the owner of a schedule or one of
 * its participants), so whoever follows up on no-shows knows. The person gets MarkedNotShown.
 */
class NotShownRecorded extends AppNotification
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly User $person,
    ) {
    }

    protected function title(): string
    {
        return __(':name was marked as not shown', ['name' => $this->person->name]);
    }

    protected function body(): string
    {
        return implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
            // a participant: whose schedule it was
            $this->schedule->user && $this->schedule->user_id !== $this->person->id
                ? __('by :name', ['name' => $this->schedule->user->name])
                : null,
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

    /**
     * The approvers (except whoever marked it, and the person marked) are told.
     */
    public static function tellApprovers(Schedule $schedule, ?User $person): void
    {
        if (! $person) {
            return;
        }

        \App\Observers\ScheduleObserver::approvers()
            ->reject(fn(User $approver) => $approver->id === $person->id)
            ->each(function (User $approver) use ($schedule, $person) {
                try {
                    $approver->notify(new self($schedule, $person));
                } catch (\Throwable $e) {
                    // a mail server hiccup must never undo or block saving the schedule
                    report($e);
                }
            });
    }
}
