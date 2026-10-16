<?php

namespace App\Notifications;

use Illuminate\Notifications\DatabaseNotification; 
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PwaWebPushNotification extends Notification
{
    use Queueable;

    protected DatabaseNotification $filamentNotification;
    /**
     * Create a new notification instance.
     */

    public function __construct(DatabaseNotification $notification)
    {
        $this->filamentNotification = $notification;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
        //return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        // The stored notification is in Filament's format: title/body live inside `data`
        $data = $this->filamentNotification->data ?? [];

        return (new WebPushMessage)
            ->title($data['title'] ?? config('app.name'))
            ->body(strip_tags((string) ($data['body'] ?? '')))
            ->icon('/img/icons/icon-192x192.png')
            ->tag($this->filamentNotification->id)
            ->data(['url' => $data['path'] ?? '/notifications']); // route inside the PWA
    }
}
