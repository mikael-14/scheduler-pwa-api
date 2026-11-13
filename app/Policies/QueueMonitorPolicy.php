<?php

declare(strict_types=1);

namespace App\Policies;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * The queue monitor (croustibat/filament-jobs-monitor, Administração → Jobs): what ran, what
 * waits and what failed. Seeing it (and retrying from it) needs view_any_queue_monitor; deleting
 * its records needs delete_queue_monitor. Nothing is created or edited by hand.
 */
class QueueMonitorPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_queue_monitor');
    }

    public function view(AuthUser $authUser, QueueMonitor $monitor): bool
    {
        return $authUser->can('view_any_queue_monitor');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, QueueMonitor $monitor): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, QueueMonitor $monitor): bool
    {
        return $authUser->can('delete_queue_monitor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_queue_monitor');
    }

    // the rest is never done here (without these methods Filament would allow them)

    public function restore(AuthUser $authUser, QueueMonitor $monitor): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, QueueMonitor $monitor): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, QueueMonitor $monitor): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
