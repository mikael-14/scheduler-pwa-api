<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Rupadana\ApiService\Models\Token;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The API plugin's tokens: your own only (a token signs in as its owner, with all of their
 * permissions); a super admin's reach every token. The list is already limited the same way.
 */
class TokenPolicy
{
    use HandlesAuthorization;

    private static function owns(AuthUser $authUser, Token $token): bool
    {
        return \App\Services\RoleAssignment::isSuperAdmin($authUser)
            || ($token->tokenable_type === $authUser->getMorphClass() && (int) $token->tokenable_id === (int) $authUser->getKey());
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_token');
    }

    public function view(AuthUser $authUser, Token $token): bool
    {
        return $authUser->can('view_token') && self::owns($authUser, $token);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_token');
    }

    public function update(AuthUser $authUser, Token $token): bool
    {
        return $authUser->can('update_token') && self::owns($authUser, $token);
    }

    public function delete(AuthUser $authUser, Token $token): bool
    {
        return $authUser->can('delete_token') && self::owns($authUser, $token);
    }

    /** The bulk delete: without this method Filament would let anyone who sees the list use it. */
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_token');
    }

    public function restore(AuthUser $authUser, Token $token): bool
    {
        return $authUser->can('restore_token');
    }

    public function forceDelete(AuthUser $authUser, Token $token): bool
    {
        return $authUser->can('force_delete_token');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_token');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_token');
    }

    public function replicate(AuthUser $authUser, Token $token): bool
    {
        return $authUser->can('replicate_token');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_token');
    }

}