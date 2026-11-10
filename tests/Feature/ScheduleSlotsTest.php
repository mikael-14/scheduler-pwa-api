<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\ScheduleTypeSlot;
use App\Models\User;
use App\Services\ScheduleTypeRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Shift slots (ScheduleSlots): a type with `uses_slots` only takes schedules on its slots; the
 * first person to take a slot books it, the next ones join it, up to its maximum of people.
 */
class ScheduleSlotsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules';

    private const VOLUNTEER = [
        'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule',
        'participate_schedule', 'cancel_participation_schedule',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        ScheduleTypeRules::flush();
    }

    private function volunteer(string $name): User
    {
        foreach (self::VOLUNTEER as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'voluntario', 'guard_name' => 'web'])->syncPermissions(self::VOLUNTEER);

        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => $name]);
        $user->assignRole('voluntario');

        return $user;
    }

    private function shiftType(): ScheduleType
    {
        return ScheduleType::create([
            'name' => 'Feira', 'color' => '#aa3300', 'status' => true, 'range' => true, 'all_day' => false,
            'primary' => false, 'uses_slots' => true,
        ]);
    }

    private function slot(ScheduleType $type, string $from = '09:00', string $to = '11:00', ?int $capacity = null, int $daysAhead = 3): ScheduleTypeSlot
    {
        $day = now()->addDays($daysAhead)->format('Y-m-d');

        return $type->slots()->create([
            'start' => "$day $from", 'end' => "$day $to", 'capacity' => $capacity, 'comment' => 'Trazer colete',
        ]);
    }

    private function take(User $user, ScheduleTypeSlot $slot)
    {
        Sanctum::actingAs($user);

        return $this->postJson(self::URL . "/slots/{$slot->id}");
    }

    public function test_the_first_person_books_the_slot_and_the_next_ones_join_it(): void
    {
        $slot = $this->slot($this->shiftType(), capacity: 3);
        [$olga, $rui] = [$this->volunteer('Olga'), $this->volunteer('Rui')];

        $first = $this->take($olga, $slot)->assertOk();
        $schedule = Schedule::sole();
        $this->assertSame($olga->id, $schedule->user_id);
        $this->assertSame($slot->id, $schedule->schedule_type_slot_id);
        $this->assertTrue($schedule->start->equalTo($slot->start));
        $this->assertTrue($schedule->end->equalTo($slot->end));
        $this->assertSame(ScheduleStatus::Pending, $schedule->status);
        $first->assertJsonPath('data.slot.comment', 'Trazer colete')->assertJsonPath('data.slot.people', 1);

        $this->take($rui, $slot)->assertOk()->assertJsonPath('data.id', $schedule->id)->assertJsonPath('data.slot.people', 2);
        $this->assertSame(1, Schedule::count());
        $this->assertSame([$rui->id], $schedule->schedule_users()->pluck('user_id')->all());

        // not twice
        $this->take($rui, $slot)->assertStatus(422)->assertJsonValidationErrors('slot');
    }

    public function test_a_full_slot_cannot_be_taken_nor_joined(): void
    {
        $slot = $this->slot($this->shiftType(), capacity: 2);
        $this->take($this->volunteer('Olga'), $slot)->assertOk();
        $this->take($this->volunteer('Rui'), $slot)->assertOk();

        $late = $this->volunteer('Teresa');
        $this->take($late, $slot)->assertStatus(422)->assertJsonPath('errors.slot.0', __('This slot is full.'));

        // nor through the usual Join
        $schedule = Schedule::sole();
        Sanctum::actingAs($late);
        $this->getJson(self::URL . "/{$schedule->id}")->assertJsonPath('data.can.join', false);
        $this->postJson(self::URL . "/{$schedule->id}/participation")->assertStatus(422);

        $this->getJson(self::URL . "/slots?schedule_type_id={$slot->schedule_type_id}")
            ->assertOk()
            ->assertJsonPath('data.0.people', 2)
            ->assertJsonPath('data.0.full', true)
            ->assertJsonPath('data.0.mine', false)
            ->assertJsonPath('data.0.schedule_id', $schedule->id);
    }

    public function test_a_type_with_slots_takes_no_other_hours(): void
    {
        $type = $this->shiftType();
        $slot = $this->slot($type);
        $other = $this->slot(ScheduleType::create(['name' => 'Outra', 'status' => true, 'range' => true, 'uses_slots' => true]));
        Sanctum::actingAs($olga = $this->volunteer('Olga'));
        $day = now()->addDays(3)->format('Y-m-d');

        // free hours
        $this->postJson(self::URL, ['schedule_type_id' => $type->id, 'start' => "$day 09:00", 'end' => "$day 11:00"])
            ->assertStatus(422)->assertJsonValidationErrors('schedule_type_slot_id');

        // booked on its slot, then moved off it
        $this->take($olga, $slot)->assertOk();
        $schedule = Schedule::sole();
        $this->putJson(self::URL . "/{$schedule->id}", [
            'schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00",
        ])->assertStatus(422)->assertJsonValidationErrors('start');

        // another type's slot
        $schedule->schedule_type_slot_id = $other->id;
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $schedule->save();
    }

    public function test_the_owner_leaving_hands_the_slot_to_the_next_person(): void
    {
        $slot = $this->slot($this->shiftType());
        [$olga, $rui, $teresa] = [$this->volunteer('Olga'), $this->volunteer('Rui'), $this->volunteer('Teresa')];
        $this->take($olga, $slot)->assertOk();
        $this->take($rui, $slot)->assertOk();
        $this->take($teresa, $slot)->assertOk();
        $schedule = Schedule::sole();

        Sanctum::actingAs($olga);
        $this->getJson(self::URL . "/{$schedule->id}")->assertJsonPath('data.can.leave', true);
        $this->deleteJson(self::URL . "/{$schedule->id}/participation")->assertOk();

        $schedule->refresh();
        $this->assertSame($rui->id, $schedule->user_id);
        $this->assertSame(ScheduleStatus::Pending, $schedule->status);
        $this->assertSame([$teresa->id], $schedule->schedule_users()->pluck('user_id')->all());
        $this->assertSame([$olga->id], $schedule->cancelled_schedule_users()->pluck('user_id')->all());

        // the last ones: Teresa leaves as usual, then Rui alone cancels it
        Sanctum::actingAs($teresa);
        $this->deleteJson(self::URL . "/{$schedule->id}/participation")->assertOk();
        Sanctum::actingAs($rui);
        $this->getJson(self::URL . "/{$schedule->id}")->assertJsonPath('data.can.leave', false)->assertJsonPath('data.can.cancel', true);

        // a cancelled slot can be booked again
        $schedule->update(['status' => ScheduleStatus::Cancelled]);
        $this->take($olga, $slot)->assertOk();
        $this->assertSame(2, Schedule::count());
    }

    public function test_a_slot_that_started_cannot_be_taken(): void
    {
        $type = $this->shiftType();
        $slot = $type->slots()->create(['start' => now()->subHour(), 'end' => now()->addHour()]);

        $this->take($this->volunteer('Olga'), $slot)->assertStatus(422)->assertJsonPath('errors.slot.0', __('This slot has already started.'));
    }

    public function test_the_form_options_tell_which_types_use_slots(): void
    {
        $type = $this->shiftType();
        Sanctum::actingAs($this->volunteer('Olga'));

        $types = collect($this->getJson(self::URL . '/form-options')->assertOk()->json('data.types'));
        $this->assertTrue($types->firstWhere('id', $type->id)['uses_slots']);
    }

    public function test_the_admin_panel_books_or_joins_the_picked_slot(): void
    {
        \Filament\Facades\Filament::setCurrentPanel('admin');
        $type = $this->shiftType();
        $slot = $this->slot($type);

        foreach ([$olga = $this->volunteer('Olga'), $rui = $this->volunteer('Rui')] as $person) {
            $this->actingAs($person, 'web');
            \Livewire\Livewire::test(\App\Filament\Resources\Schedules\Pages\CreateSchedule::class)
                ->fillForm(['schedule_type_id' => $type->id])
                ->assertFormFieldVisible('schedule_type_slot_id')
                ->fillForm(['schedule_type_id' => $type->id, 'schedule_type_slot_id' => $slot->id, 'description' => 'Na banca'])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $schedule = Schedule::sole();
        $this->assertSame($olga->id, $schedule->user_id);
        $this->assertSame('Na banca', $schedule->description);
        $this->assertTrue($schedule->start->equalTo($slot->start));
        $this->assertSame([$rui->id], $schedule->schedule_users()->pluck('user_id')->all());
    }

    public function test_slots_are_listed_with_the_people_on_them(): void
    {
        $type = $this->shiftType();
        $mine = $this->slot($type, '09:00', '11:00', capacity: 4);
        $this->slot($type, '11:00', '13:00');
        $this->slot($type, '08:00', '09:00', daysAhead: -2); // over: not listed
        $olga = $this->volunteer('Olga');
        $this->take($olga, $mine)->assertOk();

        $this->getJson(self::URL . "/slots?schedule_type_id={$type->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.mine', true)
            ->assertJsonPath('data.0.people', 1)
            ->assertJsonPath('data.0.capacity', 4)
            ->assertJsonPath('data.0.comment', 'Trazer colete')
            ->assertJsonPath('data.1.people', 0)
            ->assertJsonPath('data.1.schedule_id', null)
            ->assertJsonPath('data.1.problem', null);
    }
}
