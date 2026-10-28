<?php

namespace Tests\Feature;

use App\Filament\Resources\ScheduleTypes\Pages\CreateScheduleType;
use App\Filament\Resources\ScheduleTypes\Pages\EditScheduleType;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The admin panel's schedule type form: "primary" stays in the database, the rest are toggles. */
class ScheduleTypeFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = ['view_any_schedule_type', 'view_schedule_type', 'create_schedule_type', 'update_schedule_type'];
        $admin = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::create(['name' => 'type-admin', 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel('admin');
    }

    public function test_a_new_type_needs_an_end_time_and_has_hours_by_default(): void
    {
        Livewire::test(CreateScheduleType::class)
            ->assertFormSet(['status' => true, 'range' => true, 'all_day' => false])
            ->assertFormFieldExists('range')
            ->assertFormFieldExists('all_day')
            ->assertFormFieldDoesNotExist('primary')
            ->assertSee(__('Needs an end time'))
            ->assertSee(__('Active'))
            ->fillForm(['name' => 'Walks', 'color' => '#336699'])
            ->call('create')
            ->assertHasNoFormErrors();

        $type = ScheduleType::where('name', 'Walks')->firstOrFail();
        $this->assertTrue($type->range);
        $this->assertFalse($type->all_day);
        $this->assertFalse((bool) $type->primary);
    }

    public function test_saving_a_type_keeps_it_primary(): void
    {
        $type = ScheduleType::create([
            'name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => true,
        ]);

        Livewire::test(EditScheduleType::class, ['record' => $type->getKey()])
            ->fillForm(['name' => 'Cleaning the boxes'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Cleaning the boxes', $type->fresh()->name);
        $this->assertTrue((bool) $type->fresh()->primary);
    }
}
