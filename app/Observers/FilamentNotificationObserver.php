<?php 

namespace App\Observers;

// 1. Change this namespace to use standard Laravel model
use Illuminate\Notifications\DatabaseNotification; 
use App\Notifications\PwaWebPushNotification;

class FilamentNotificationObserver
{
    /**
     * Handle the DatabaseNotification "created" event.
     */
    public function created(DatabaseNotification $notification): void
    {
        // 2. Fetch the user model attached to this notification
        $user = $notification->notifiable;

        if ($user) {
            // Forward it straight to your PWA push notification channel!
            $user->notify(new PwaWebPushNotification($notification));
        }
    }
}