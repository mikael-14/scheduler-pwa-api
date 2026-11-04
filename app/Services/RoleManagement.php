<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The Roles screen never reaches above yourself (the same idea as UserManagement):
 * - the super admin role is only edited by a super admin, and never deleted;
 * - nobody edits or deletes a role they have themselves (no giving yourself more, nor locking
 *   yourself out); a super admin may;
 * - a role is edited / deleted only by who has every permission it has;
 * - a role is saved only with permissions that exist and that the person saving it has
 *   (Shield's pages would create any name the browser sends).
 * Used by RolePolicy and the app's role pages (App\Filament\Resources\Roles).
 */
class RoleManagement
{
    public static function mayManage(?User $actor, ?Role $role): bool
    {
        if (! $actor || ! $role) {
            return false;
        }

        if (RoleAssignment::isSuperAdmin($actor)) {
            return true;
        }

        if (static::isSuperAdminRole($role) || $actor->hasRole($role->name)) {
            return false;
        }

        return $role->permissions()->pluck('name')->diff(static::permissionsOf($actor))->isEmpty();
    }

    public static function mayDelete(?User $actor, ?Role $role): bool
    {
        return $role && ! static::isSuperAdminRole($role) && static::mayManage($actor, $role);
    }

    /**
     * The chosen permission names that may be saved: existing ones, and (except for a super admin)
     * only ones $actor has.
     */
    public static function allowed(?User $actor, Collection $chosen, string $guard = 'web'): Collection
    {
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name');
        $allowed = $chosen->filter(fn($name) => is_string($name))->unique()->intersect($existing);

        if (! RoleAssignment::isSuperAdmin($actor)) {
            $allowed = $allowed->intersect(static::permissionsOf($actor));
        }

        return $allowed->values();
    }

    public static function isSuperAdminRole(Role $role): bool
    {
        return $role->name === \BezhanSalleh\FilamentShield\Support\Utils::getSuperAdminName();
    }

    private static function permissionsOf(?User $actor): Collection
    {
        return $actor?->getAllPermissions()->pluck('name') ?? collect();
    }
}
