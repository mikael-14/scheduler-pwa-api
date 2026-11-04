<?php

namespace App\Services;

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\Models\Role;

/**
 * Who may give roles to whom, on the user create / edit pages:
 * - only with `assign_role_user` (seeing the roles, `view_any_role`, is not enough)
 * - never one's own roles (no promoting or locking out oneself)
 * - the super admin role, and the roles of a super admin, only by a super admin
 * - never to someone with permissions one lacks, nor a role with permissions one lacks
 *   (UserManagement: never above yourself)
 * The saved list is checked again on the server, whatever the browser sends.
 */
class RoleAssignment
{
    public const PERMISSION = 'assign_role_user';

    public static function isSuperAdmin(?User $user): bool
    {
        return (bool) $user?->hasRole(Utils::getSuperAdminName());
    }

    /** $target null: a user being created. */
    public static function canAssign(?User $actor, ?User $target = null): bool
    {
        if (! $actor?->can(self::PERMISSION)) {
            return false;
        }
        if (! $target) {
            return true;
        }

        // never one's own; never above oneself (a super admin, someone with more permissions)
        return UserManagement::mayManageOther($actor, $target);
    }

    /**
     * @return array<int, string> id => name, the roles $actor may give: super admin only by a
     * super admin, and only roles whose permissions $actor has too (no giving more than you have)
     */
    public static function options(?User $actor): array
    {
        $superAdmin = self::isSuperAdmin($actor);
        $mine = $actor?->getAllPermissions()->pluck('name') ?? collect();

        return Role::query()
            ->with('permissions:id,name')
            ->where('guard_name', 'web')
            ->when(! $superAdmin, fn($query) => $query->where('name', '!=', Utils::getSuperAdminName()))
            ->orderBy('name')
            ->get()
            ->filter(fn(Role $role) => $superAdmin || $role->permissions->pluck('name')->diff($mine)->isEmpty())
            ->pluck('name', 'id')
            ->all();
    }

    /** Gives $target exactly the chosen roles (ones $actor may not give are ignored); nothing if $actor may not. */
    public static function sync(?User $actor, User $target, mixed $roleIds): void
    {
        if (! self::canAssign($actor, $target)) {
            return;
        }

        $allowed = array_keys(self::options($actor));
        $chosen = array_values(array_intersect($allowed, array_map('intval', (array) $roleIds)));

        $target->syncRoles(Role::query()->whereIn('id', $chosen)->get());
    }
}
