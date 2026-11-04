<?php

namespace App\Policies;

use App\Models\User;
use App\Services\UserManagement;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Shield permissions; on other people also UserManagement: never above yourself (a super admin,
 * or someone with permissions you lack), and never deleting yourself.
 */
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

    public function update(AuthUser $authUser, User $user): bool
    {
        if ($authUser->can('update_any_user')) {
            return $authUser->id === $user->id || UserManagement::mayManageOther($authUser, $user);
        }
        if ($authUser->can('update_owned_user')) {
            return $authUser->id === $user->id;
        }
        return false;
    }

    public function delete(AuthUser $authUser, User $user): bool
    {
        return $authUser->can('delete_user') && UserManagement::mayManageOther($authUser, $user);
    }

    /** The bulk delete: without this method Filament would let anyone who sees the list use it. */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_user');
    }

    public function restore(AuthUser $authUser, User $user): bool
    {
        return $authUser->can('restore_user') && UserManagement::mayManageOther($authUser, $user);
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
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }

    public function viewOwned(AuthUser $authUser): bool
    {
        return $authUser->can('view_owned_user');
    }
    
    public function updateOwned(AuthUser $authUser): bool
    {
        return $authUser->can('update_owned_user');
    }

    public function updateAny(AuthUser $authUser): bool
    {
        return $authUser->can('update_any_user');
    }

    public function impersonate(AuthUser $authUser): bool
    {
        return $authUser->can('impersonate_user');
    }

    public function approve(AuthUser $authUser): bool
    {
        return $authUser->can('approve_user');
    }

    /** Give roles to others (App\Services\RoleAssignment has the rest of the rules). */
    public function assignRole(AuthUser $authUser): bool
    {
        return $authUser->can('assign_role_user');
    }

}