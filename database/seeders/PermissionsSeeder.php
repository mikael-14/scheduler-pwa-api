<?php

namespace Database\Seeders;

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the permissions table in line with the code, using Shield's own commands:
 *
 *   php artisan db:seed --class=PermissionsSeeder
 *
 * - shield:generate (permissions only) creates every permission declared by the panel's
 *   resources, pages and widgets, including the custom ones from config/filament-shield.php
 *   (resources.manage). It never touches the hand-written policies and is safe to re-run.
 * - Permissions no longer declared (old widgets, actions nothing uses) are deleted, so the Roles
 *   screen only shows what does something. Ones renamed are first copied onto the roles that had
 *   them (RENAMED), so nobody loses access.
 * - The super_admin role is then given every permission, so new permissions reach it on each run.
 * - If SUPER_ADMIN_EMAIL is set and that user exists, they are given the role (shield:super-admin).
 *
 * Other roles keep the permissions they already have: new ones must be granted to them in the
 * Roles screen of the admin panel.
 */
class PermissionsSeeder extends Seeder
{
    /** old name => the permission that does the same now */
    private const RENAMED = [
        'update_user' => 'update_any_user',
    ];

    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);
        $this->command?->getOutput()->write(Artisan::output());

        $this->removeUndeclared();

        $superAdmin = Utils::createRole();
        $superAdmin->syncPermissions(Utils::getPermissionModel()::pluck('id'));
        $this->command?->info("Role '{$superAdmin->name}' now has " . $superAdmin->permissions()->count() . ' permissions.');

        $email = env('SUPER_ADMIN_EMAIL');
        $user = $email ? User::where('email', $email)->first() : null;

        if ($user) {
            Artisan::call('shield:super-admin', ['--user' => $user->id, '--panel' => 'admin']);
            $this->command?->info("{$email} is a super admin.");
        } elseif ($email) {
            $this->command?->warn("SUPER_ADMIN_EMAIL={$email} does not match any user; role not assigned.");
        }
    }

    private function removeUndeclared(): void
    {
        $permission = Utils::getPermissionModel();
        $declared = FilamentShield::getEntitiesPermissions();

        foreach (self::RENAMED as $old => $new) {
            $oldOne = $permission::where('name', $old)->first();
            $newOne = $permission::where('name', $new)->first();
            if ($oldOne && $newOne) {
                foreach ($oldOne->roles as $role) {
                    $role->givePermissionTo($newOne);
                }
            }
        }

        $stale = $permission::whereNotIn('name', $declared)->get();
        foreach ($stale as $old) {
            $old->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($stale->isNotEmpty()) {
            $this->command?->info('Removed ' . $stale->count() . ' permissions no longer used: ' . $stale->pluck('name')->implode(', '));
        }
    }
}
