<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Managing users never reaches above yourself: not a super admin, not someone with more permissions. */
class UserAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['view_any_user', 'view_owned_user', 'create_user', 'update_any_user', 'update_owned_user', 'delete_user', 'restore_user', 'impersonate_user'];

    private function userWith(array $permissions, ?string $roleName = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName ?? 'r' . uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_an_admin_cannot_edit_delete_or_impersonate_a_super_admin(): void
    {
        $super = $this->userWith(self::ADMIN, 'super_admin');
        $admin = $this->userWith(self::ADMIN);
        $this->actingAs($admin);

        $this->assertFalse($admin->can('update', $super), 'update');
        $this->assertFalse($admin->can('delete', $super), 'delete');
        $this->assertFalse($super->canBeImpersonated($admin), 'impersonate');
        $this->get("/admin/users/{$super->id}/edit")->assertForbidden();
    }

    public function test_nobody_takes_over_someone_with_more_permissions(): void
    {
        $boss = $this->userWith([...self::ADMIN, 'approve_schedule', 'view_any_audit']);
        $helper = $this->userWith(['view_any_user', 'update_any_user', 'impersonate_user', 'delete_user']);
        $novo = $this->userWith(['view_any_user']);
        $this->actingAs($helper);

        $this->assertFalse($helper->can('update', $boss));
        $this->assertFalse($helper->can('delete', $boss));
        $this->assertFalse($boss->canBeImpersonated($helper));

        // the same or fewer permissions: fine
        $this->assertTrue($helper->can('update', $novo));
        $this->assertTrue($helper->can('delete', $novo));
        $this->assertTrue($novo->canBeImpersonated($helper));

        // the email (social login finds accounts by it) is not changed either
        Livewire::test(EditUser::class, ['record' => $boss->getRouteKey()])->assertForbidden();
    }

    public function test_nobody_deletes_or_impersonates_themselves(): void
    {
        $admin = $this->userWith(self::ADMIN);
        $this->actingAs($admin);

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($admin->canBeImpersonated($admin));
        $this->assertTrue($admin->can('update', $admin));

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertActionHidden('delete');
    }

    public function test_only_roles_within_your_own_permissions_are_given(): void
    {
        $helper = $this->userWith(['view_any_user', 'update_any_user', 'assign_role_user', 'view_any_schedule']);
        $small = Role::create(['name' => 'Externo', 'guard_name' => 'web'])
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'view_any_schedule', 'guard_name' => 'web']));
        $big = Role::create(['name' => 'Admin', 'guard_name' => 'web'])
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'delete_user', 'guard_name' => 'web']));
        $target = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($helper);

        $options = \App\Services\RoleAssignment::options($helper);
        $this->assertContains('Externo', $options);
        $this->assertNotContains('Admin', $options);

        \App\Services\RoleAssignment::sync($helper, $target, [$small->id, $big->id]);
        $this->assertSame(['Externo'], $target->fresh()->roles->pluck('name')->all());
    }

    public function test_bulk_delete_skips_who_you_could_not_delete_one_by_one(): void
    {
        $boss = $this->userWith([...self::ADMIN, 'view_any_audit']);
        $helper = $this->userWith(['view_any_user', 'delete_user']);
        $novo = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($helper);

        Livewire::test(ListUsers::class)
            ->selectTableRecords([$boss->id, $novo->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertSoftDeleted($novo);
        $this->assertNotSoftDeleted($boss);
    }
}
