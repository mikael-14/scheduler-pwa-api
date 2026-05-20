<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ScheduleType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScheduleTypePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ScheduleType');
    }

    public function view(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('View:ScheduleType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ScheduleType');
    }

    public function update(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('Update:ScheduleType');
    }

    public function delete(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('Delete:ScheduleType');
    }

    public function restore(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('Restore:ScheduleType');
    }

    public function forceDelete(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('ForceDelete:ScheduleType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ScheduleType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ScheduleType');
    }

    public function replicate(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('Replicate:ScheduleType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ScheduleType');
    }

}