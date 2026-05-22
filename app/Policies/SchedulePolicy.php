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

    public function editOwned(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('edit_owned_schedule');
    }

    public function deleteOwned(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('delete_owned_schedule');
    }

    public function editPast(AuthUser $authUser, Schedule $schedule): bool
    {
        return $authUser->can('edit_past_schedule');
    }

}