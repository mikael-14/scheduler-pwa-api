<?php

namespace App\Notifications;

use Illuminate\Notifications\DatabaseNotification;

/**
 * How long the push service keeps trying a push while the phone is off or offline (TTL), and
 * which pushes replace each other while they wait (Topic): a phone back after a day gets the latest
 * of each topic, not a flood. What is about the person themselves never collapses and waits longest.
 * The stored notification (the app's list) is always complete; this is only about the push copies.
 */
final class PushDelivery
{
    private const HOUR = 3600;

    private const DAY = 86400;

    /** About the person (decisions, added / removed, booked for them, changes, account, announcements). */
    private const PERSONAL_TTL = 7 * self::DAY;

    /** Requests waiting for approvers: they are decided in the app, where all of them are listed. */
    private const TO_DECIDE = [
        SchedulePendingApproval::class,
        ParticipationRequested::class,
        NotShownRecorded::class,
    ];

    /**
     * @return array{topic: ?string, ttl: int}
     */
    public static function for(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];

        return match (true) {
            // "all notifications": other people's schedules, stale within hours
            isset($data['activity']) => ['topic' => 'activity', 'ttl' => 6 * self::HOUR],
            $notification->type === ScheduleJoinedOrLeft::class => ['topic' => 'joined-left', 'ttl' => self::DAY],
            in_array($notification->type, self::TO_DECIDE, true) => ['topic' => 'to-decide', 'ttl' => 3 * self::DAY],
            // each coverage run sends the current summary: the newest replaces the one still waiting
            $notification->type === CoverageAlert::class => [
                'topic' => ($data['level'] ?? null) === CoverageAlert::AHEAD ? 'coverage-ahead' : 'coverage-urgent',
                'ttl' => self::DAY,
            ],
            default => ['topic' => null, 'ttl' => self::PERSONAL_TTL],
        };
    }
}
