<?php

namespace Tests\Feature;

use App\Filament\Resources\ScheduleTypes\Pages\EditScheduleType;
use App\Filament\Resources\ScheduleTypes\Pages\ListScheduleTypes;
use App\Filament\Resources\ScheduleTypes\Pages\ViewScheduleType;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Schedule types in the admin panel: each screen and button needs its own permission. */
class ScheduleTypeAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $role = Role::create(['name' => 'r' . uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function type(string $name = 'Walks'): ScheduleType
    {
        return ScheduleType::create(['name' => $name, 'color' => '#123456', 'status' => true, 'range' => true, 'all_day' => false]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_pages_need_their_permission(): void
    {
        $type = $this->type();

        $this->actingAs($this->userWith('view_any_schedule'))->get('/admin/schedule-types')->assertForbidden();

        $lister = $this->userWith('view_any_schedule_type');
        $this->actingAs($lister)->get('/admin/schedule-types')->assertOk();
        $this->actingAs($lister)->get("/admin/schedule-types/{$type->id}")->assertForbidden();
        $this->actingAs($lister)->get("/admin/schedule-types/{$type->id}/edit")->assertForbidden();
        $this->actingAs($lister)->get('/admin/schedule-types/create')->assertForbidden();

        $viewer = $this->userWith('view_any_schedule_type', 'view_schedule_type');
        $this->actingAs($viewer)->get("/admin/schedule-types/{$type->id}")->assertOk();
        $this->actingAs($viewer)->get("/admin/schedule-types/{$type->id}/edit")->assertForbidden();
    }

    public function test_deleting_needs_delete_permission_in_the_table_and_in_bulk(): void
    {
        $type = $this->type();
        $this->actingAs($this->userWith('view_any_schedule_type', 'view_schedule_type', 'update_schedule_type'));

        Livewire::test(ListScheduleTypes::class)
            ->assertActionHidden(TestAction::make('delete')->table($type))
            ->assertActionHidden(TestAction::make('delete')->table()->bulk());

        $this->assertModelExists($type);
    }

    public function test_bulk_delete_keeps_types_with_schedules(): void
    {
        $free = $this->type('Free');
        $used = $this->type('Used');
        \App\Models\Schedule::withoutEvents(fn() => \App\Models\Schedule::create([
            'schedule_type_id' => $used->id, 'user_id' => User::factory()->create()->id,
            'status' => \App\Enums\ScheduleStatus::Pending, 'start' => now()->addDay(), 'end' => now()->addDay()->addHour(),
        ]));

        $this->actingAs($this->userWith('view_any_schedule_type', 'delete_schedule_type'));

        Livewire::test(ListScheduleTypes::class)
            ->selectTableRecords([$free->id, $used->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertModelMissing($free);
        $this->assertModelExists($used);
    }
}
