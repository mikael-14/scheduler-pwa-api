<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

/**
 * The login history (IPs, devices, of everyone): only for who may see the audits, the admins.
 * Without a policy the plugin's page (/admin/authentication-logs) and the "logins" tab on a user
 * were open to every active user. Read-only: the history is never edited by hand.
 */
class AuthenticationLogPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_audit');
    }

    public function view(AuthUser $authUser, AuthenticationLog $log): bool
    {
        return $authUser->can('view_any_audit');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, AuthenticationLog $log): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, AuthenticationLog $log): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    // every Filament ability answers, so none is open by default
    public function restore(AuthUser $authUser, AuthenticationLog $log): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, AuthenticationLog $log): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, AuthenticationLog $log): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
