<?php

namespace Tests\Feature;

use App\Filament\Actions\ApproveUserAction;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Who gave or took which role / permission is in the audits, like every other change. */
class RoleAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // tests run in the console, where model audits are off by default
        config(['audit.console' => true]);
        Model::clearBootedModels();
    }

    private function audits(Model $model, string $event)
    {
        return Audit::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->where('event', $event)
            ->orderBy('id')
            ->get();
    }

    public function test_giving_and_changing_roles_is_one_entry_each_with_who_did_it(): void
    {
        $assign = Permission::create(['name' => RoleAssignment::PERMISSION, 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web'])->givePermissionTo($assign);
        $novo = Role::create(['name' => 'Voluntário Novo', 'guard_name' => 'web']);
        $experiente = Role::create(['name' => 'Voluntário Experiente', 'guard_name' => 'web']);
        $admin = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $admin->assignRole($adminRole);
        $volunteer = User::factory()->create(['approved_at' => null, 'status' => true]);
        $this->actingAs($admin);

        // approved with a role
        ApproveUserAction::approve($volunteer, $novo->id);
        // and later moved to another one (all removed, then the new one: one entry)
        RoleAssignment::sync($admin, $volunteer->fresh(), [$experiente->id]);
        // the same roles again: nothing new
        RoleAssignment::sync($admin, $volunteer->fresh(), [$experiente->id]);

        $entries = $this->audits($volunteer, 'roles');
        $this->assertCount(2, $entries);
        $this->assertSame(['', 'Voluntário Novo'], [$entries[0]->old_values['roles'], $entries[0]->new_values['roles']]);
        $this->assertSame(['Voluntário Novo', 'Voluntário Experiente'], [$entries[1]->old_values['roles'], $entries[1]->new_values['roles']]);
        $this->assertSame($admin->id, (int) $entries[1]->user_id);
    }

    public function test_a_roles_permissions_and_its_name_are_in_its_history(): void
    {
        $admin = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($admin);
        $view = Permission::create(['name' => 'view_any_schedule', 'guard_name' => 'web']);
        $join = Permission::create(['name' => 'participate_schedule', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Externo', 'guard_name' => 'web']);

        $role->givePermissionTo($view);
        // the Roles screen replaces the whole list
        $role->syncPermissions([$view, $join]);
        $role->revokePermissionTo($view);
        $role->update(['name' => 'Externos']);

        $entries = $this->audits($role, 'permissions');
        $this->assertSame([
            ['', 'view_any_schedule'],
            ['view_any_schedule', 'participate_schedule, view_any_schedule'],
            ['participate_schedule, view_any_schedule', 'participate_schedule'],
        ], $entries->map(fn($audit) => [$audit->old_values['permissions'], $audit->new_values['permissions']])->all());

        $renamed = $this->audits($role, 'updated')->last();
        $this->assertSame(['Externo', 'Externos'], [$renamed->old_values['name'], $renamed->new_values['name']]);
    }
}
