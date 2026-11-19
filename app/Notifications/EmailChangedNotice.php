<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the OLD address when the email of an account is changed from the PWA, so its owner
 * hears about it even if someone else did it (e.g. from a stolen phone).
 * Not queued: a warning about the account should leave right away.
 */
class EmailChangedNotice extends Notification
{
    public function __construct(
        private readonly string $name,
        private readonly string $newEmail,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('The email of your account was changed'))
            ->greeting(__('Hello :name,', ['name' => $this->name]))
            ->line(__('The email address of your account was changed to :email.', ['email' => $this->newEmail]))
            ->line(__('If you did not do this, contact an administrator right away.'));
    }
}
