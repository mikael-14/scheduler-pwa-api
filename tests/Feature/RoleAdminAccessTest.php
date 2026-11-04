<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The Roles screen never reaches above yourself (RoleManagement). */
class RoleAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role'];

    private function role(string $name, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $role;
    }

    private function userWith(Role $role): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_the_app_screen_replaces_shields(): void
    {
        $resources = Filament::getPanel('admin')->getResources();
        $this->assertContains(RoleResource::class, $resources);
        $this->assertNotContains(\BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::class, $resources);

        $this->actingAs($this->userWith($this->role('Admin', self::ROLES)));
        $this->get(RoleResource::getUrl('index'))->assertOk();
    }

    public function test_nobody_edits_their_own_role_the_super_admin_role_or_a_bigger_one(): void
    {
        $admin = $this->userWith($this->role('Admin', [...self::ROLES, 'view_any_schedule']));
        $super = $this->role('super_admin', self::ROLES);
        $bigger = $this->role('Auditor', ['view_any_audit']);
        $smaller = $this->role('Externo', ['view_any_schedule']);
        $this->actingAs($admin);

        foreach ([Role::findByName('Admin'), $super, $bigger] as $role) {
            $this->assertFalse($admin->can('update', $role), "update {$role->name}");
            $this->assertFalse($admin->can('delete', $role), "delete {$role->name}");
            $this->get(RoleResource::getUrl('edit', ['record' => $role]))->assertForbidden();
        }
        $this->assertTrue($admin->can('update', $smaller));
        $this->assertTrue($admin->can('delete', $smaller));

        // a super admin edits anything, but never deletes the super admin role
        $boss = $this->userWith($super);
        $this->assertTrue($boss->can('update', $super));
        $this->assertFalse($boss->can('delete', $super));
        $this->assertTrue($boss->can('update', $bigger));
    }

    public function test_only_permissions_you_have_are_saved_and_no_new_names(): void
    {
        $admin = $this->userWith($this->role('Admin', [...self::ROLES, 'view_any_schedule', 'participate_schedule']));
        Permission::firstOrCreate(['name' => 'delete_user', 'guard_name' => 'web']);
        $externo = $this->role('Externo', ['view_any_schedule']);
        $this->actingAs($admin);

        $page = Livewire::test(EditRole::class, ['record' => $externo->getRouteKey()]);
        $data = $page->instance()->data;

        // an invented name is refused by the form (and would not be created)
        $page->instance()->data = array_merge($data, [\App\Filament\Resources\Schedules\ScheduleResource::class => ['view_any_schedule', 'made_up_permission']]);
        try {
            $page->instance()->save();
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->assertFalse(Permission::where('name', 'made_up_permission')->exists());
        $this->assertSame(['view_any_schedule'], $externo->fresh()->permissions->pluck('name')->all());

        // what the browser sends: permissions the admin has, and one they lack
        $page->instance()->data = array_merge($data, [
            \App\Filament\Resources\Schedules\ScheduleResource::class => ['view_any_schedule', 'participate_schedule'],
            \App\Filament\Resources\Users\UserResource::class => ['delete_user'],
        ]);
        $page->instance()->save();
        $this->assertEqualsCanonicalizing(['view_any_schedule', 'participate_schedule'], $externo->fresh()->permissions->pluck('name')->all());

        // the same when creating one
        $create = Livewire::test(CreateRole::class);
        $create->instance()->data = ['name' => 'Novo', 'guard_name' => 'web', \App\Filament\Resources\Schedules\ScheduleResource::class => ['participate_schedule'], \App\Filament\Resources\Users\UserResource::class => ['delete_user']];
        $create->instance()->create();
        $this->assertSame(['participate_schedule'], Role::findByName('Novo')->permissions->pluck('name')->all());
    }

    public function test_bulk_delete_skips_the_roles_you_could_not_delete_one_by_one(): void
    {
        $admin = $this->userWith($this->role('Admin', self::ROLES));
        $super = $this->role('super_admin');
        $bigger = $this->role('Auditor', ['view_any_audit']);
        $free = $this->role('Old');
        $this->actingAs($admin);

        Livewire::test(ListRoles::class)
            ->selectTableRecords([$super->id, $bigger->id, $free->id, Role::findByName('Admin')->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertNull(Role::where('name', 'Old')->first());
        $this->assertNotNull(Role::where('name', 'super_admin')->first());
        $this->assertNotNull(Role::where('name', 'Auditor')->first());
        $this->assertNotNull(Role::where('name', 'Admin')->first());
    }
}
