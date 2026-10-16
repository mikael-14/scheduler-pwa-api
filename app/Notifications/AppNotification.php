<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base of the app's user-facing notifications.
 *
 * - `database`: always stored. It feeds the bell of the admin panel (Filament format) and the
 *   notification list of the PWA. FilamentNotificationObserver then forwards every stored
 *   notification as web push to the PWA when the user has push enabled and a subscription.
 * - `mail`: only when the user turned on email notifications in their profile.
 *
 * User preferences live on users.email_notification / users.push_notification. The text is built in
 * the recipient's language because User implements HasLocalePreference.
 */
abstract class AppNotification extends Notification
{
    abstract protected function title(): string;

    abstract protected function body(): string;

    /** success | danger | warning | info */
    protected function status(): string
    {
        return 'info';
    }

    /** Where the PWA should take the user. */
    protected function path(): string
    {
        return '/notifications';
    }

    /**
     * Absolute link for places outside the PWA (the email button and the panel bell).
     * When null, the PWA path is used.
     */
    protected function url(): ?string
    {
        return null;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email_notification && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** Filament's database format, so the panel's bell renders it natively. */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title())
            ->body($this->body());

        match ($this->status()) {
            'success' => $notification->success(),
            'danger' => $notification->danger(),
            'warning' => $notification->warning(),
            default => $notification->info(),
        };

        if ($url = $this->url()) {
            $notification->actions([
                Action::make('view')->label(__('View'))->url($url)->markAsRead(),
            ]);
        }

        return array_merge($notification->getDatabaseMessage(), ['path' => $this->path()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->body())
            ->action(
                __('Open the scheduler'),
                $this->url() ?? rtrim((string) config('app.pwa_url'), '/') . $this->path()
            )
            ->line(__('You are receiving this email because email notifications are enabled in your profile.'));
    }
}
