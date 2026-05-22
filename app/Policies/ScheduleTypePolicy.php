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

    public function restore(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('restore_schedule_type');
    }

    public function forceDelete(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('force_delete_schedule_type');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_schedule_type');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_schedule_type');
    }

    public function replicate(AuthUser $authUser, ScheduleType $scheduleType): bool
    {
        return $authUser->can('replicate_schedule_type');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_schedule_type');
    }

}