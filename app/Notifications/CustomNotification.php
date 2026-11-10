<?php

namespace App\Notifications;

/**
 * A message written by an admin in the "Sent notifications" screen (e.g. the shelter is closed
 * tomorrow). Delivered like every other notification: in the app always, by email and push
 * following each person's preferences, or by email to everyone when marked as important.
 */
class CustomNotification extends AppNotification
{
    /** Can wait for the email summary (see AppNotification::digestible). */
    protected function digestible(): bool
    {
        return true;
    }

    public function __construct(
        private readonly string $subject,
        private readonly string $message,
        private readonly string $level = 'info',
        private readonly bool $emailEveryone = false,
    ) {
    }

    protected function title(): string
    {
        return $this->subject;
    }

    protected function body(): string
    {
        return $this->message;
    }

    protected function status(): string
    {
        return $this->level;
    }

    protected function alwaysEmail(): bool
    {
        return $this->emailEveryone;
    }
}
