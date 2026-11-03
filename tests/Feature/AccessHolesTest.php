<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Services\RoleAssignment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The login history is for admins only; roles are given only as RoleAssignment allows. */
class AccessHolesTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $role;
    }

    private function userWith(Role ...$roles): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_the_login_history_is_only_for_who_sees_the_audits(): void
    {
        $volunteer = $this->userWith($this->role('volunteer', ['view_any_schedule']));
        $admin = $this->userWith($this->role('admin', ['view_any_audit']));

        $this->actingAs($volunteer)->get('/admin/authentication-logs')->assertForbidden();
        $this->actingAs($admin)->get('/admin/authentication-logs')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_seeing_the_roles_is_not_enough_to_give_them_not_even_to_oneself(): void
    {
        Filament::setCurrentPanel('admin');
        $super = $this->role('super_admin');
        $viewer = $this->userWith($this->role('viewer', ['view_any_role', 'update_owned_user', 'view_owned_user']));

        Livewire::actingAs($viewer)->test(EditUser::class, ['record' => $viewer->getRouteKey()])
            ->assertFormFieldIsDisabled('role')
            ->set('data.role', [$super->id])
            ->call('save');

        $this->assertFalse($viewer->fresh()->hasRole('super_admin'));
        $this->assertTrue($viewer->fresh()->hasRole('viewer'));
    }

    #[RunInSeparateProcess]
    public function test_an_admin_gives_exactly_the_chosen_roles_but_never_super_admin(): void
    {
        Filament::setCurrentPanel('admin');
        $super = $this->role('super_admin', [RoleAssignment::PERMISSION]); // as the seeder makes it: every permission
        $novo = $this->role('Voluntário Novo');
        $experiente = $this->role('Voluntário Experiente');
        $admin = $this->userWith($this->role('admin', ['view_any_user', 'update_any_user', 'view_any_role', RoleAssignment::PERMISSION]));
        $volunteer = $this->userWith($novo);

        $this->actingAs($admin);
        $this->assertArrayNotHasKey($super->id, RoleAssignment::options($admin));

        // super_admin sent anyway: refused, nothing changes
        Livewire::test(EditUser::class, ['record' => $volunteer->getRouteKey()])
            ->assertFormFieldIsEnabled('role')
            ->set('data.role', [$experiente->id, $super->id])
            ->call('save')
            ->assertHasErrors();
        $this->assertSame(['Voluntário Novo'], $volunteer->fresh()->getRoleNames()->all());

        // and if it got past the form, the save drops it too
        RoleAssignment::sync($admin, $volunteer, [$novo->id, $super->id]);
        $this->assertSame(['Voluntário Novo'], $volunteer->fresh()->getRoleNames()->all());

        // unticking Novo removes it
        Livewire::test(EditUser::class, ['record' => $volunteer->getRouteKey()])
            ->set('data.role', [$experiente->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['Voluntário Experiente'], $volunteer->fresh()->getRoleNames()->all());

        // own roles: never
        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldIsDisabled('role');

        // a super admin's roles: only by a super admin
        $boss = $this->userWith($super);
        RoleAssignment::sync($admin, $boss, []);
        $this->assertTrue($boss->fresh()->hasRole('super_admin'));

        RoleAssignment::sync($boss, $volunteer, [$super->id]);
        $this->assertTrue($volunteer->fresh()->hasRole('super_admin'));
    }

    #[RunInSeparateProcess]
    public function test_creating_a_user_gives_roles_only_with_the_permission(): void
    {
        Filament::setCurrentPanel('admin');
        $this->role('Voluntário Novo');
        $creator = $this->userWith($this->role('creator', ['view_any_user', 'create_user', 'view_any_role']));

        Livewire::actingAs($creator)->test(CreateUser::class)
            ->assertFormFieldIsHidden('role')
            // no approve_user: the account is created waiting for approval
            ->assertFormFieldIsDisabled('approved');
    }

    #[RunInSeparateProcess]
    public function test_on_their_own_record_nobody_changes_active_approved_or_the_key(): void
    {
        Filament::setCurrentPanel('admin');
        $self = $this->userWith($this->role('self', ['view_owned_user', 'update_owned_user']));
        $approvedAt = $self->approved_at;

        Livewire::actingAs($self)->test(EditUser::class, ['record' => $self->getRouteKey()])
            ->assertFormFieldIsDisabled('status')
            ->assertFormFieldIsDisabled('approved')
            ->assertFormFieldIsDisabled('building_key')
            ->set('data.status', false)
            ->set('data.approved', false)
            ->set('data.building_key', true)
            ->set('data.name', 'New Name')
            ->call('save')
            ->assertHasNoFormErrors();

        $self->refresh();
        $this->assertSame('New Name', $self->name);
        $this->assertTrue((bool) $self->status);
        $this->assertFalse((bool) $self->building_key);
        $this->assertEquals($approvedAt, $self->approved_at);

        // not even an admin, on their own record
        $admin = $this->userWith($this->role('admin', ['view_any_user', 'update_any_user', 'approve_user']));
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldIsDisabled('status')
            ->assertFormFieldIsDisabled('building_key');
    }

    #[RunInSeparateProcess]
    public function test_approving_from_the_form_needs_approve_user_and_keeps_the_date(): void
    {
        Filament::setCurrentPanel('admin');
        $editor = $this->userWith($this->role('editor', ['view_any_user', 'update_any_user']));
        $waiting = User::factory()->create(['approved_at' => null, 'status' => true]);

        Livewire::actingAs($editor)->test(EditUser::class, ['record' => $waiting->getRouteKey()])
            ->assertFormFieldIsEnabled('building_key')
            ->assertFormFieldIsDisabled('approved')
            ->set('data.approved', true)
            ->set('data.building_key', true)
            ->call('save');

        $this->assertNull($waiting->fresh()->approved_at);
        $this->assertTrue((bool) $waiting->fresh()->building_key);

        // an approver: approves, and saving again later keeps the first date
        $approver = $this->userWith($this->role('approver', ['view_any_user', 'update_any_user', 'approve_user']));
        $old = User::factory()->create(['approved_at' => now()->subYear(), 'status' => true]);
        $date = $old->approved_at;

        Livewire::actingAs($approver)->test(EditUser::class, ['record' => $old->getRouteKey()])
            ->assertFormFieldIsEnabled('approved')
            ->set('data.name', 'Still Approved')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertEquals($date, $old->fresh()->approved_at);
    }
}
