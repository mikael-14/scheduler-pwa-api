<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\DeliveryLog;
use App\Notifications\PwaWebPushNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Every notification stored for a user (the app's own and Filament's) is also sent as web push
 * to the PWA, but only when the user has push enabled and has a device subscribed.
 */
class FilamentNotificationObserver
{
    public function created(DatabaseNotification $notification): void
    {
        $user = $notification->notifiable;

        if (! $user instanceof User || ! $user->push_notification || ! $user->pushSubscriptions()->exists()) {
            return;
        }

        try {
            DeliveryLog::queuedPush($notification->id);
            $user->notify(new PwaWebPushNotification($notification));
        } catch (\Throwable $e) {
            DeliveryLog::pushFailed($notification->id, $e);
            report($e);
        }
    }
}
