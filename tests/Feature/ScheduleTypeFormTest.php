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

    public function test_slots_are_entered_as_a_day_and_two_times_and_must_fit_the_hours(): void
    {
        $day = now()->addDays(10)->format('Y-m-d');

        // outside the type's hours: refused
        Livewire::test(CreateScheduleType::class)
            ->fillForm([
                'name' => 'Feira', 'color' => '#aa3300', 'max_time' => '18:00', 'uses_slots' => true,
                'slots' => [['day' => $day, 'from' => '17:00', 'to' => '19:00', 'capacity' => null, 'comment' => null]],
            ])
            ->call('create')
            ->assertHasFormErrors();
        $this->assertFalse(ScheduleType::where('name', 'Feira')->exists());

        Livewire::test(CreateScheduleType::class)
            ->fillForm([
                'name' => 'Feira', 'color' => '#aa3300', 'uses_slots' => true,
                'slots' => [
                    ['day' => $day, 'from' => '09:00', 'to' => '11:00', 'capacity' => 5, 'comment' => 'Trazer colete'],
                    ['day' => $day, 'from' => '22:00', 'to' => '00:00', 'capacity' => null, 'comment' => null],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $type = ScheduleType::where('name', 'Feira')->firstOrFail();
        $this->assertTrue($type->uses_slots);
        [$morning, $night] = $type->slots()->get()->all();
        $this->assertSame("$day 09:00 → $day 11:00", $morning->start->format('Y-m-d H:i') . ' → ' . $morning->end->format('Y-m-d H:i'));
        $this->assertSame(5, $morning->capacity);
        $this->assertSame('Trazer colete', $morning->comment);
        $this->assertNull($night->capacity);
        // until midnight: the next day at 00:00
        $this->assertSame(now()->addDays(11)->format('Y-m-d') . ' 00:00', $night->end->format('Y-m-d H:i'));

        // editing shows them back as a day and two times
        $edit = Livewire::test(EditScheduleType::class, ['record' => $type->getKey()]);
        $items = collect($edit->instance()->data['slots'])->values();
        $this->assertSame($day, substr((string) $items[0]['day'], 0, 10));
        $this->assertSame(['09:00', '11:00'], [substr($items[0]['from'], 0, 5), substr($items[0]['to'], 0, 5)]);

        $edit->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, $type->slots()->count());
        $this->assertSame("$day 09:00", $type->slots()->first()->start->format('Y-m-d H:i'));
    }
}
