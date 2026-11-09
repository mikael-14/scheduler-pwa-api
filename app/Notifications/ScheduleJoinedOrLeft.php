<?php

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\User;

/**
 * Someone joined a schedule or was added to it, or left it or was removed. Sent to its owner, the
 * other people on it and the approvers (ScheduleUserObserver), never to whoever did it.
 */
class ScheduleJoinedOrLeft extends AppNotification
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly User $participant,
        public readonly bool $joined,
        /** the recipient owns the schedule ("your schedule") */
        private readonly bool $yours = true,
        /** someone else did it (added / removed), not the person themselves (joined / left) */
        private readonly bool $byOthers = false,
    ) {
    }

    protected function title(): string
    {
        $name = ['name' => $this->participant->name];

        if ($this->yours) {
            return match (true) {
                $this->joined && $this->byOthers => __(':name was added to your schedule', $name),
                $this->joined => __(':name joined your schedule', $name),
                // leaving is cancelling the attendance (they stay on it as cancelled)
                $this->byOthers => __('The attendance of :name on your schedule was cancelled', $name),
                default => __(':name cancelled their attendance on your schedule', $name),
            };
        }

        return match (true) {
            $this->joined && $this->byOthers => __(':name was added to a schedule', $name),
            $this->joined => __(':name joined a schedule', $name),
            $this->byOthers => __('The attendance of :name on a schedule was cancelled', $name),
            default => __(':name cancelled their attendance on a schedule', $name),
        };
    }

    /** In the app and on the phone: the email's first sentence (who, what and when). */
    protected function body(): string
    {
        return $this->mailLines()[0];
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }

    /** By email, in full sentences (the app keeps the short text). */
    protected function mailLines(): array
    {
        $what = [
            'name' => $this->participant->name,
            'type' => $this->schedule->schedule_type?->name ?? __('Schedule'),
            'date' => $this->schedule->start?->format(config('app.date_time_format')),
            'owner' => $this->schedule->user?->name,
        ];

        if ($this->joined) {
            return [
                match (true) {
                    $this->yours && $this->byOthers => __(':name was added to your :type on :date.', $what),
                    $this->yours => __(':name joined your :type on :date.', $what),
                    $this->byOthers => __(':name was added to :type on :date (by :owner).', $what),
                    default => __(':name joined :type on :date (by :owner).', $what),
                },
            ];
        }

        return array_values(array_filter([
            match (true) {
                $this->yours && $this->byOthers => __('The attendance of :name at your :type on :date was cancelled.', $what),
                $this->yours => __(':name cancelled their attendance at your :type on :date.', $what),
                $this->byOthers => __('The attendance of :name at :type on :date (by :owner) was cancelled.', $what),
                default => __(':name cancelled their attendance at :type on :date (by :owner).', $what),
            },
            __('They are no longer counted among the people going. They can join again until it starts.'),
        ]));
    }

    protected function mailActionLabel(): string
    {
        return __('Open the schedule');
    }

    /** Stored with the notification, so the app can tell joining from leaving. */
    public function toDatabase(object $notifiable): array
    {
        return parent::toDatabase($notifiable) + ['joined' => $this->joined];
    }
}
