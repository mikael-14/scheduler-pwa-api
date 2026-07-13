<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Schedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_schedule');
    }

    public function view(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('view_schedule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_schedule');
    }

    public function update(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('update_schedule');
    }

    public function delete(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('delete_schedule');
    }

    public function restore(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('restore_schedule');
    }

    public function forceDelete(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('force_delete_schedule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_schedule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_schedule');
    }

    public function replicate(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('replicate_schedule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_schedule');
    }

    public function createAny(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('create_any_schedule');
    }

    public function updateAny(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('update_any_schedule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_schedule');
    }

    public function updatePast(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('update_past_schedule');
    }

    public function participate(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('participate_schedule');
    }

    public function independent(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('independent_schedule');
    }

    public function updateStatus(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('update_status_schedule');
    }

    public function approve(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('approve_schedule');
    }

}