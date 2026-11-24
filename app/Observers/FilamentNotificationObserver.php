<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\CoverageAlert;
use App\Notifications\DeliveryLog;
use App\Notifications\PwaWebPushNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Every notification stored for a user (the app's own and Filament's) is also sent as web push
 * to the PWA, but only when the user has push enabled for it (see wantsPush) and has a device
 * subscribed.
 */
class FilamentNotificationObserver
{
    public function created(DatabaseNotification $notification): void
    {
        $user = $notification->notifiable;

        if (! $user instanceof User || ! static::wantsPush($user, $notification) || ! $user->pushSubscriptions()->exists()) {
            return;
        }

        try {
            DeliveryLog::queuedPush($notification->id);
            $push = new PwaWebPushNotification($notification);

            // the urgent coverage summary is stored together with the look-ahead one: pushed a bit
            // later, since some phones drop or merge pushes that arrive in the same second
            if ($notification->type === CoverageAlert::class && ($notification->data['level'] ?? null) === CoverageAlert::URGENT) {
                $push->delay(now()->addSeconds(CoverageAlert::PUSH_GAP_SECONDS));
            }

            $user->notify($push);
        } catch (\Throwable $e) {
            DeliveryLog::pushFailed($notification->id, $e);
            report($e);
        }
    }

    /**
     * Agenda activity about other people's schedules (ScheduleActivity) follows
     * all_push_notification; everything else follows push_notification.
     */
    public static function wantsPush(User $user, DatabaseNotification $notification): bool
    {
        return isset($notification->data['activity'])
            ? (bool) $user->all_push_notification
            : (bool) $user->push_notification;
    }
}
