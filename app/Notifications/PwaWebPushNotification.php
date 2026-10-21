<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The web push copy of a stored notification (see FilamentNotificationObserver). Queued: sending
 * means one HTTPS call to the push service per subscribed device. Expired subscriptions are
 * removed by the channel itself. How it went is recorded on the stored row (DeliveryLog).
 */
class PwaWebPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected DatabaseNotification $filamentNotification)
    {
    }

    /** The id of the stored notification this push is a copy of. */
    public function storedId(): string
    {
        return $this->filamentNotification->id;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // checked again when the job runs: push may have been turned off in the meantime
        if (! \App\Observers\FilamentNotificationObserver::wantsPush($notifiable, $this->filamentNotification)) {
            DeliveryLog::pushNotSent($this->storedId());

            return [];
        }

        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        // The stored notification is in Filament's format: title/body live inside `data`
        $data = $this->filamentNotification->data ?? [];

        return (new WebPushMessage)
            ->title($data['title'] ?? config('app.name'))
            ->body(strip_tags((string) ($data['body'] ?? '')))
            ->icon('/img/icons/icon-192x192.png')
            ->tag($this->storedId()) // also how DeliveryLog finds the row again
            ->data(['url' => $data['path'] ?? '/notifications']) // route inside the PWA
            // high: delivered at once even on a locked, idle phone (Android Doze holds back
            // "normal" pushes); TTL: the push service keeps trying for a day if the phone is off
            ->options(['urgency' => 'high', 'TTL' => 86400]);
    }

    /** The queued job gave up (e.g. the push service could not be reached). */
    public function failed(\Throwable $e): void
    {
        DeliveryLog::pushFailed($this->storedId(), $e);
    }
}
