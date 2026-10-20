<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotificationLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Shield permissions for the "Sent notifications" list. Rows are never edited or deleted;
 * new ones come from the app, or from an admin with `send_notification_log` writing an
 * announcement (resources.manage in config/filament-shield.php).
 */
class NotificationLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_notification_log');
    }

    public function view(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('view_notification_log');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    /** Write an announcement to everyone, some roles or some people. */
    public function send(AuthUser $authUser): bool
    {
        return $authUser->can('send_notification_log');
    }

    public function update(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
