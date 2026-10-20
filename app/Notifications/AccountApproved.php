<?php

namespace App\Notifications;

class AccountApproved extends AppNotification
{
    protected function title(): string
    {
        return __('Your account was approved');
    }

    protected function body(): string
    {
        return __('You can now use the scheduler.');
    }

    protected function status(): string
    {
        return 'success';
    }

    protected function path(): string
    {
        return '/';
    }

    /** A user waiting for approval is not using the app, so the in-app notice alone would go unseen. */
    protected function alwaysEmail(): bool
    {
        return true;
    }
}
