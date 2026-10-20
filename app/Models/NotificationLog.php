<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Notifications\AccountApproved;
use App\Notifications\SchedulePendingApproval;
use App\Notifications\ScheduleStatusChanged;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The `notifications` table seen as a log, for the admin panel's "Sent notifications" list.
 * A separate class, so the push observer on DatabaseNotification never fires from here.
 */
class NotificationLog extends DatabaseNotification
{
    protected $table = 'notifications';

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'mail_status' => DeliveryStatus::class,
            'mail_sent_at' => 'datetime',
            'push_status' => DeliveryStatus::class,
            'push_sent_at' => 'datetime',
        ];
    }

    /** Every notifiable in this app is a user. */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notifiable_id')->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    /** @return array<class-string, string> notification class => readable name */
    public static function typeOptions(): array
    {
        return [
            ScheduleStatusChanged::class => __('Schedule status changed'),
            SchedulePendingApproval::class => __('Schedule awaiting approval'),
            \App\Notifications\ScheduleParticipantAdded::class => __('Added to a schedule'),
            \App\Notifications\ScheduleParticipantRemoved::class => __('Removed from a schedule'),
            \App\Notifications\ScheduleBookedForYou::class => __('Booked for you'),
            \App\Notifications\ScheduleUpdated::class => __('Schedule changed'),
            \App\Notifications\CoverageAlert::class => __('Coverage alert'),
            AccountApproved::class => __('Account approved'),
            \App\Notifications\CustomNotification::class => __('Announcement'),
            \Filament\Notifications\DatabaseNotification::class => __('Admin panel message'),
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? class_basename($this->type);
    }
}
