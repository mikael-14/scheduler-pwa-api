<?php

namespace App\Services;

use App\Models\User;

/**
 * Managing other people never reaches above yourself. Editing someone (their email: social login
 * finds accounts by it), switching them off, deleting, restoring or impersonating them is only
 * for who has every permission they have; a super admin only by a super admin. Nobody deletes or
 * impersonates themselves. Used by UserPolicy and User::canBeImpersonated, on top of the
 * permissions (update_any_user, delete_user, ...).
 */
class UserManagement
{
    public static function mayManage(?User $actor, ?User $target): bool
    {
        if (! $actor || ! $target) {
            return false;
        }

        if (RoleAssignment::isSuperAdmin($actor)) {
            return true;
        }

        if (RoleAssignment::isSuperAdmin($target)) {
            return false;
        }

        $mine = $actor->getAllPermissions()->pluck('name');

        return $target->getAllPermissions()->pluck('name')->diff($mine)->isEmpty();
    }

    /** Someone else, within mayManage. */
    public static function mayManageOther(?User $actor, ?User $target): bool
    {
        return $actor && $target && ! $target->is($actor) && static::mayManage($actor, $target);
    }
}
