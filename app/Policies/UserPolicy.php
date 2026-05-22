<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_user') || $authUser->can('view_owned_user');
    }

    public function view(AuthUser $authUser, User $user): bool
    {
        // Admin permission → can view anyone
        if ($authUser->can('view_any_user')) {
            return true;
        }

        // Normal permission → can only view himself
        if ($authUser->can('view_owned_user')) {
            return $authUser->id === $user->id;
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_user');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('update_user');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('delete_user');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('restore_user');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_user');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('replicate_user');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_user');
    }

    public function viewOwned(AuthUser $authUser): bool
    {
        return $authUser->can('view_owned_user');
    }

    public function impersonate(AuthUser $authUser): bool
    {
        return $authUser->can('impersonate_user');
    }

}