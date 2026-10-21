<?php

namespace App\Notifications;

use App\Models\Schedule;

/**
 * "All notifications": what happens on the agenda, sent to everyone who turned it on, also for
 * schedules they are not part of, so they think of joining (see App\Services\ScheduleActivityFeed).
 *
 * Stored like every notification (the person's history). Email when `all_email_notification` is
 * on, push when `all_push_notification` is on (the stored row carries `activity`, see
 * FilamentNotificationObserver), independent of the personal notification settings.
 */
class ScheduleActivity extends AppNotification
{
    public const CREATED = 'created';
    public const APPROVED = 'approved';
    public const CHANGED = 'changed';
    public const CANCELLED = 'cancelled';
    public const REJECTED = 'rejected';
    public const JOINED = 'joined';
    public const LEFT = 'left';

    /** Names and ids only: the queued email is written later, the schedule may have changed by then. */
    private readonly int $scheduleId;
    private readonly ?string $typeName;
    private readonly ?string $ownerName;
    private readonly string $dateText;

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changes CHANGED: from ScheduleUpdated::captureChanges()
     */
    public function __construct(
        Schedule $schedule,
        public readonly string $event,
        private readonly ?string $participantName = null,
        private readonly array $changes = [],
    ) {
        $schedule->loadMissing(['schedule_type', 'user']);

        $this->scheduleId = $schedule->id;
        $this->typeName = $schedule->schedule_type?->name;
        $this->ownerName = $schedule->user?->name;
        $this->dateText = (string) $schedule->start?->format($schedule->all_day
            ? config('app.date_format')
            : config('app.date_time_format'));
    }

    protected function title(): string
    {
        $names = [
            'name' => $this->ownerName ?? __('Someone'),
            'type' => $this->typeName ?? __('a schedule'),
            'participant' => $this->participantName ?? __('Someone'),
        ];

        return match ($this->event) {
            self::CREATED => __(':name booked :type', $names),
            self::APPROVED => __(':type of :name was approved', $names),
            self::CHANGED => __(':type of :name was changed', $names),
            self::CANCELLED => __(':name cancelled :type', $names),
            self::REJECTED => __(':type of :name was rejected', $names),
            self::JOINED => __(':participant joined :type of :name', $names),
            self::LEFT => __(':participant left :type of :name', $names),
            default => __('Agenda activity'),
        };
    }

    protected function body(): string
    {
        $lines = [implode(' · ', array_filter([$this->typeName, $this->dateText]))];

        $lines = match ($this->event) {
            self::CREATED => [...$lines, __('Waiting for approval. Want to join?')],
            self::CHANGED => [...$lines, ...ScheduleUpdated::changeLines($this->changes)],
            self::CANCELLED, self::REJECTED, self::LEFT => [...$lines, __('Can you go?')],
            default => $lines,
        };

        return implode("\n", $lines);
    }

    protected function status(): string
    {
        return match ($this->event) {
            self::APPROVED => 'success',
            self::CANCELLED, self::REJECTED, self::LEFT => 'warning',
            default => 'info',
        };
    }

    /** Cancelled / rejected schedules may be hidden from volunteers: open the agenda instead. */
    protected function path(): string
    {
        return in_array($this->event, [self::CANCELLED, self::REJECTED], true)
            ? '/agenda'
            : "/schedules/{$this->scheduleId}";
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->all_email_notification && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** `activity` tells the push observer to follow all_push_notification (and the app the kind). */
    public function toDatabase(object $notifiable): array
    {
        return parent::toDatabase($notifiable) + ['activity' => $this->event];
    }
}
