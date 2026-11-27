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
        $delivery = PushDelivery::for($this->filamentNotification);

        return (new WebPushMessage)
            ->title($data['title'] ?? config('app.name'))
            ->body(strip_tags((string) ($data['body'] ?? '')))
            ->icon('/img/icons/icon-192x192.png')
            ->tag($this->storedId()) // also how DeliveryLog finds the row again
            ->data([
                'url' => $data['path'] ?? '/notifications', // route inside the PWA
                // the number on the app icon, and the service worker's summary when several pile up
                // (written in the recipient's language: notifications are sent in their locale)
                'unread' => $notifiable->unreadNotifications()->count(),
                'summary_title' => __('You have :count unread notifications'),
            ])
            // high: delivered at once even on a locked, idle phone (Android Doze holds back
            // "normal" pushes); TTL / Topic: how long the push service keeps trying while the phone
            // is off, and which waiting pushes replace each other (PushDelivery)
            ->options(array_filter([
                'urgency' => 'high',
                'TTL' => $delivery['ttl'],
                'topic' => $delivery['topic'],
            ], fn($value) => $value !== null));
    }

    /** The queued job gave up (e.g. the push service could not be reached). */
    public function failed(\Throwable $e): void
    {
        DeliveryLog::pushFailed($this->storedId(), $e);
    }
}
