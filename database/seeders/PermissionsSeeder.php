<?php

namespace Database\Seeders;

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Brings the permissions table in line with the code, using Shield's own commands:
 *
 *   php artisan db:seed --class=PermissionsSeeder
 *
 * - shield:generate (permissions only) creates every permission declared by the panel's
 *   resources, pages and widgets, including the custom ones from config/filament-shield.php
 *   (resources.manage). It never touches the hand-written policies and is safe to re-run.
 * - The super_admin role is then given every permission, so new permissions reach it on each run.
 * - If SUPER_ADMIN_EMAIL is set and that user exists, they are given the role (shield:super-admin).
 *
 * Other roles keep the permissions they already have: new ones must be granted to them in the
 * Roles screen of the admin panel.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);
        $this->command?->getOutput()->write(Artisan::output());

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
}
