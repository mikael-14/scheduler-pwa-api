<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Reset your password" email for the PWA: the link opens the PWA's reset screen.
 * The admin panel keeps its own reset email (Filament's), which links to the panel.
 * Not queued on purpose: someone locked out is waiting for it right now.
 * Written in the user's language (User implements HasLocalePreference).
 */
class PwaResetPassword extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.pwa_url'), '/') . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We received a request to reset the password of your account.'))
            ->action(__('Choose a new password'), $url)
            ->line(__('This link expires in :count minutes.', ['count' => $minutes]))
            ->line(__('If you did not ask for this, you can ignore this email: your password stays the same.'));
    }
}
