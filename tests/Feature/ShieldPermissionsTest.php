<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The custom permissions the app relies on are produced by Shield itself from
 * config/filament-shield.php (resources.manage), not by a hand-written seeder.
 */
class ShieldPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function generate(): void
    {
        // permissions only: never let the generator rewrite the hand-written policies
        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'admin', '--option' => 'permissions']);
    }

    public function test_generate_creates_every_permission_the_code_checks(): void
    {
        $this->generate();

        $expected = [
            // standard
            'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule',
            'view_any_schedule_type', 'view_schedule_type', 'create_schedule_type', 'update_schedule_type', 'delete_schedule_type',
            'view_any_user', 'create_user', 'update_user', 'delete_user',
            // custom, declared under resources.manage
            'create_any_schedule', 'update_any_schedule', 'delete_any_schedule', 'update_past_schedule',
            'participate_schedule', 'independent_schedule', 'update_status_schedule', 'approve_schedule',
            'approve_user', 'impersonate_user', 'view_owned_user', 'update_owned_user', 'update_any_user',
            'restore_audit',
        ];

        $existing = Permission::pluck('name')->all();

        $this->assertSame([], array_values(array_diff($expected, $existing)), 'permissions missing after shield:generate');
    }

    public function test_generate_is_idempotent(): void
    {
        $this->generate();
        $count = Permission::count();

        $this->generate();

        $this->assertSame($count, Permission::count());
    }

    public function test_seeder_generates_permissions_and_keeps_super_admin_complete(): void
    {
        $admin = User::factory()->create(['email' => 'boss@x.test', 'approved_at' => now(), 'status' => true]);
        putenv('SUPER_ADMIN_EMAIL=boss@x.test');
        $_ENV['SUPER_ADMIN_EMAIL'] = 'boss@x.test';

        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $role = Role::where('name', 'super_admin')->firstOrFail();
        $this->assertGreaterThan(0, Permission::count());
        $this->assertSame(Permission::count(), $role->permissions()->count());
        $this->assertTrue($admin->fresh()->can('create_any_schedule'));

        // A permission added later (e.g. a new resource) reaches super_admin on the next run
        Permission::create(['name' => 'brand_new_permission', 'guard_name' => 'web']);
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('brand_new_permission'));

        putenv('SUPER_ADMIN_EMAIL');
        unset($_ENV['SUPER_ADMIN_EMAIL']);
    }

    public function test_no_orphan_permissions_for_self_gated_pages_and_widgets(): void
    {
        $this->generate();

        $this->assertSame(0, Permission::where('name', 'like', '%schedule_calendar%')->count());
        $this->assertSame(0, Permission::where('name', 'like', '%schedules_widget%')->count());
        $this->assertSame(0, Permission::where('name', 'like', '%headcount_chart%')->count());
    }

    public function test_super_admin_command_grants_every_permission(): void
    {
        $this->generate();
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);

        Artisan::call('shield:super-admin', ['--user' => $user->id, '--panel' => 'admin']);

        $role = Role::where('name', 'super_admin')->firstOrFail();
        $this->assertSame(Permission::count(), $role->permissions()->count());
        $this->assertTrue($user->fresh()->hasRole('super_admin'));
        $this->assertTrue($user->fresh()->can('update_past_schedule'));
    }
}
