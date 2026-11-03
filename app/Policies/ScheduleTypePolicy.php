<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ScheduleType;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The permissions declared for schedule types (config/filament-shield.php). Every ability Filament
 * asks about is here: a missing method would let anyone through (e.g. deleteAny, the bulk delete).
 * Types are not soft deleted, so there is no restore / force delete.
 */
class ScheduleTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_schedule_type');
    }

    public function view(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('view_schedule_type');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_schedule_type');
    }

    public function update(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('update_schedule_type');
    }

    public function delete(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('delete_schedule_type');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_schedule_type');
    }

    public function restore(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
