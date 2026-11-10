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
use Tests\TestCase;

/**
 * Defining the slots of a type from the PWA (api/admin/schedule-types, SlotDefinitions): the
 * whole list is sent; a slot with people on it keeps its times and can't be removed.
 */
class ScheduleTypeSlotsApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedule-types';

    protected function setUp(): void
    {
        parent::setUp();
        ScheduleTypeRules::flush();
        foreach (['view_schedule_type', 'create_schedule_type', 'update_schedule_type'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->givePermissionTo(['view_schedule_type', 'create_schedule_type', 'update_schedule_type']);
        Sanctum::actingAs($user);
    }

    private function day(int $ahead = 3): string
    {
        return now()->addDays($ahead)->format('Y-m-d');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Feira', 'color' => '#aa3300', 'status' => true, 'range' => true, 'all_day' => false,
            'start' => null, 'end' => null, 'description' => null, 'min_time' => '08:00', 'max_time' => '20:00',
            'uses_slots' => true,
        ], $overrides);
    }

    /** A schedule on the slot: it is taken. */
    private function book(ScheduleTypeSlot $slot): void
    {
        Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $slot->schedule_type_id, 'schedule_type_slot_id' => $slot->id,
            'user_id' => User::factory()->create()->id, 'status' => ScheduleStatus::Pending,
            'start' => $slot->start, 'end' => $slot->end,
        ]));
    }

    public function test_a_type_is_created_with_its_slots(): void
    {
        $day = $this->day();
        $this->postJson(self::URL, $this->payload(['slots' => [
            ['day' => $day, 'from' => '09:00', 'to' => '11:00', 'capacity' => 4, 'comment' => 'Trazer colete'],
            ['day' => $day, 'from' => '11:00', 'to' => '13:00'],
        ]]))
            ->assertOk()
            ->assertJsonPath('data.uses_slots', true)
            ->assertJsonCount(2, 'data.slots')
            ->assertJsonPath('data.slots.0.from', '09:00')
            ->assertJsonPath('data.slots.0.capacity', 4)
            ->assertJsonPath('data.slots.0.comment', 'Trazer colete')
            ->assertJsonPath('data.slots.1.capacity', null)
            ->assertJsonPath('data.slots.1.taken', false);

        $type = ScheduleType::sole();
        $this->assertTrue($type->uses_slots);
        $this->assertSame(2, $type->slots()->count());

        $this->getJson(self::URL . "/{$type->id}")->assertOk()->assertJsonCount(2, 'data.slots');
    }

    public function test_slots_outside_the_type_hours_are_refused(): void
    {
        $this->postJson(self::URL, $this->payload(['slots' => [
            ['day' => $this->day(), 'from' => '06:00', 'to' => '09:00'],
            ['day' => $this->day(), 'from' => '12:00', 'to' => '10:00'],
        ]]))->assertStatus(422)->assertJsonValidationErrors(['slots.0.to', 'slots.1.to']);

        $this->assertSame(0, ScheduleType::count());
    }

    public function test_slots_are_changed_added_and_removed_but_taken_ones_stay(): void
    {
        $type = ScheduleType::create(collect($this->payload())->except('slots')->all());
        $day = $this->day();
        $taken = $type->slots()->create(['start' => "$day 09:00", 'end' => "$day 11:00", 'capacity' => 2]);
        $free = $type->slots()->create(['start' => "$day 11:00", 'end' => "$day 13:00"]);
        $gone = $type->slots()->create(['start' => "$day 14:00", 'end' => "$day 16:00"]);
        $this->book($taken);
        $url = self::URL . "/{$type->id}";

        // a taken slot can't be moved
        $this->putJson($url, $this->payload(['slots' => [
            ['id' => $taken->id, 'day' => $day, 'from' => '10:00', 'to' => '12:00'],
            ['id' => $free->id, 'day' => $day, 'from' => '11:00', 'to' => '13:00'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('slots.0.from');

        // nor removed
        $this->putJson($url, $this->payload(['slots' => [
            ['id' => $free->id, 'day' => $day, 'from' => '11:00', 'to' => '13:00'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('slots');

        // its maximum and comment change; the free one moves; one is new; the last is removed
        $this->putJson($url, $this->payload(['slots' => [
            ['id' => $taken->id, 'day' => $day, 'from' => '09:00', 'to' => '11:00', 'capacity' => 5, 'comment' => 'Portão B'],
            ['id' => $free->id, 'day' => $day, 'from' => '12:00', 'to' => '14:00'],
            ['day' => $this->day(4), 'from' => '08:00', 'to' => '12:00'],
        ]]))
            ->assertOk()
            ->assertJsonCount(3, 'data.slots')
            ->assertJsonPath('data.slots.0.taken', true)
            ->assertJsonPath('data.slots.0.people', 1);

        $this->assertSame(5, $taken->fresh()->capacity);
        $this->assertSame('Portão B', $taken->fresh()->comment);
        $this->assertSame('12:00', $free->fresh()->start->format('H:i'));
        $this->assertNull(ScheduleTypeSlot::find($gone->id));
        $this->assertSame(3, $type->slots()->count());
    }

    public function test_slots_cannot_be_defined_without_the_type_permissions(): void
    {
        $type = ScheduleType::create(collect($this->payload())->except('slots')->all());
        $day = $this->day();
        $slot = $type->slots()->create(['start' => "$day 09:00", 'end' => "$day 11:00", 'comment' => 'Original']);

        // can see types, but not create or change them
        $viewer = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $viewer->givePermissionTo('view_schedule_type');
        Sanctum::actingAs($viewer);

        $this->postJson(self::URL, $this->payload(['slots' => [
            ['day' => $day, 'from' => '09:00', 'to' => '11:00'],
        ]]))->assertForbidden();

        $this->putJson(self::URL . "/{$type->id}", $this->payload(['slots' => [
            ['id' => $slot->id, 'day' => $day, 'from' => '12:00', 'to' => '14:00', 'comment' => 'Changed'],
            ['day' => $day, 'from' => '15:00', 'to' => '17:00'],
        ]]))->assertForbidden();

        $this->putJson(self::URL . "/{$type->id}", $this->payload(['slots' => []]))->assertForbidden();

        $this->assertSame(1, ScheduleType::count());
        $this->assertSame(1, $type->slots()->count());
        $this->assertSame('Original', $slot->fresh()->comment);
        $this->assertSame('09:00', $slot->fresh()->start->format('H:i'));

        // nobody signed in
        $this->app['auth']->forgetGuards();
        $this->putJson(self::URL . "/{$type->id}", $this->payload(['slots' => []]))->assertUnauthorized();
        $this->assertSame(1, $type->slots()->count());
    }

    public function test_another_types_slot_is_refused_and_leaving_slots_out_keeps_them(): void
    {
        $type = ScheduleType::create(collect($this->payload())->except('slots')->all());
        $other = ScheduleType::create(['name' => 'Outra', 'status' => true, 'range' => true]);
        $day = $this->day();
        $mine = $type->slots()->create(['start' => "$day 09:00", 'end' => "$day 11:00"]);
        $theirs = $other->slots()->create(['start' => "$day 09:00", 'end' => "$day 11:00"]);

        $this->putJson(self::URL . "/{$type->id}", $this->payload(['slots' => [
            ['id' => $theirs->id, 'day' => $day, 'from' => '09:00', 'to' => '11:00'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('slots.0.id');

        // an older app that doesn't send slots: nothing changes
        $this->putJson(self::URL . "/{$type->id}", collect($this->payload(['name' => 'Feira 2']))->except('uses_slots')->all())->assertOk();
        $this->assertTrue($type->fresh()->uses_slots);
        $this->assertNotNull($mine->fresh());
        $this->assertNotNull($theirs->fresh());
    }
}
