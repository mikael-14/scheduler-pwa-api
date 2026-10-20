<?php

namespace App\Notifications;

use App\Enums\DeliveryStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records on the stored notification (the `notifications` row) how its email and push copies went:
 *
 *   database row written ─ mail_status = queued when `mail` is one of its channels (Channels\DatabaseChannel)
 *                        └ push_status = queued when a push copy is queued (FilamentNotificationObserver)
 *   email sent           ─ mail_status = sent    (Laravel's NotificationSent event, `mail` channel)
 *   push reached a device ─ push_status = sent   (webpush package's NotificationSent event)
 *   failures             ─ *_status = failed + delivery_error
 *
 * The email shares the row's id (Laravel gives every channel of one notification the same id); the
 * push copy carries it as its tag. Plain query updates: no model events, so no observer loops.
 */
class DeliveryLog
{
    public static function queuedPush(string $notificationId): void
    {
        self::update($notificationId, ['push_status' => DeliveryStatus::Queued->value]);
    }

    /**
     * A queued push that never reached a device: without a reason it was simply not sent (push
     * turned off meanwhile), with one it failed (e.g. no device left).
     */
    public static function pushNotSent(string $notificationId, ?string $reason = null): void
    {
        self::query($notificationId)
            ->where('push_status', DeliveryStatus::Queued->value)
            ->update($reason
                ? ['push_status' => DeliveryStatus::Failed->value, 'delivery_error' => $reason]
                : ['push_status' => null]);
    }

    public static function mailSent(string $notificationId): void
    {
        self::update($notificationId, ['mail_status' => DeliveryStatus::Sent->value, 'mail_sent_at' => now()]);
    }

    public static function mailFailed(string $notificationId, Throwable|string $error): void
    {
        self::update($notificationId, ['mail_status' => DeliveryStatus::Failed->value, 'delivery_error' => self::message($error)]);
    }

    /** One device reached is enough to call the push delivered. */
    public static function pushSent(string $notificationId): void
    {
        self::update($notificationId, ['push_status' => DeliveryStatus::Sent->value, 'push_sent_at' => now()]);
    }

    /** A device refused it; only a failure while no other device has received it yet. */
    public static function pushFailed(string $notificationId, Throwable|string $error): void
    {
        self::query($notificationId)
            ->where(fn($query) => $query->whereNull('push_status')->orWhere('push_status', '!=', DeliveryStatus::Sent->value))
            ->update(['push_status' => DeliveryStatus::Failed->value, 'delivery_error' => self::message($error)]);
    }

    private static function update(string $notificationId, array $values): void
    {
        self::query($notificationId)->update($values);
    }

    private static function query(string $notificationId)
    {
        return DB::table('notifications')->where('id', $notificationId);
    }

    private static function message(Throwable|string $error): string
    {
        return Str::limit($error instanceof Throwable ? $error->getMessage() : $error, 1000);
    }
}
