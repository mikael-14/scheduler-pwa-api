<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base of the app's user-facing notifications.
 *
 * - `database`: always stored. It feeds the bell of the admin panel (Filament format) and the
 *   notification list of the PWA. FilamentNotificationObserver then forwards every stored
 *   notification as web push to the PWA when the user has push enabled and a subscription.
 * - `mail`: when the user has email notifications on in their profile, or always for account
 *   messages (alwaysEmail()).
 *
 * User preferences live on users.email_notification / users.push_notification. The text is built in
 * the recipient's language because User implements HasLocalePreference.
 *
 * Queued, and only after the surrounding transaction commits: the bell entry is written right
 * away (sync), while email waits for a queue worker so a slow mail server never slows down or
 * fails an approval. Web push is queued too (see PwaWebPushNotification).
 */
abstract class AppNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

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

    /**
     * The queued email gave up (mail server down, bad address...). Recorded on the stored row,
     * which shares this notification's id; the in-app copy itself is written synchronously.
     */
    public function failed(\Throwable $e): void
    {
        DeliveryLog::mailFailed($this->id, $e);
    }

    /** Account messages the user must get even with email notifications turned off. */
    protected function alwaysEmail(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (($this->alwaysEmail() || $notifiable->email_notification) && filled($notifiable->email)) {
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
            // one paragraph per line, so a multi-line message keeps its shape
            ->lines(array_filter(array_map('trim', preg_split('/\R/', $this->body()))))
            ->action(
                __('Open the scheduler'),
                $this->url() ?? rtrim((string) config('app.pwa_url'), '/') . $this->path()
            )
            ->lineIf(
                ! $this->alwaysEmail(),
                __('You are receiving this email because email notifications are enabled in your profile.')
            );
    }
}
