<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Managing schedule types from the PWA (api/admin/schedule-types): each action needs its own
 * permission, a type with schedules can't be deleted, 'primary' is never set from the API,
 * and every change lands in the audit log with its author.
 */
class ScheduleTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['view_any_schedule_type', 'view_schedule_type', 'create_schedule_type', 'update_schedule_type', 'delete_schedule_type'];

    protected function setUp(): void
    {
        parent::setUp();
        // Laravel Auditing skips console runs (tests count as console); API requests are audited
        config(['audit.console' => true]);
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function actingWith(array $permissions): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);

        return $user;
    }

    private function type(array $attributes = []): ScheduleType
    {
        return ScheduleType::create(array_merge(
            ['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => false, 'all_day' => false],
            $attributes,
        ));
    }

    /** A schedule of the type (no notifications: not what is tested here). */
    private function scheduleOf(ScheduleType $type): Schedule
    {
        $day = now()->addDays(3)->format('Y-m-d');

        return Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => User::factory()->create()->id,
            'status' => \App\Enums\ScheduleStatus::Pending, 'start' => "$day 10:00", 'end' => "$day 12:00",
        ]));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Walks', 'color' => '#00aa55', 'status' => true, 'range' => true, 'all_day' => false,
            'start' => '2026-11-01 09:00', 'end' => '2026-12-31 18:00', 'description' => 'Dog walks',
            'min_time' => '08:00', 'max_time' => '20:00',
        ], $overrides);
    }

    public function test_list_needs_view_any_and_says_what_each_type_allows(): void
    {
        $busy = $this->type(['name' => 'Busy']);
        $free = $this->type(['name' => 'Free']);
        $this->scheduleOf($busy);

        $this->actingWith([]);
        $this->getJson('/api/admin/schedule-types')->assertForbidden();

        $this->actingWith(['view_any_schedule_type', 'delete_schedule_type']);
        $rows = collect($this->getJson('/api/admin/schedule-types')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(1, $rows['Busy']['schedules_count']);
        $this->assertFalse($rows['Busy']['can']['delete']);
        $this->assertTrue($rows['Free']['can']['delete']);
        $this->assertFalse($rows['Free']['can']['update']);
    }

    public function test_create_needs_permission_ignores_primary_and_is_audited(): void
    {
        $this->actingWith(['view_any_schedule_type']);
        $this->postJson('/api/admin/schedule-types', $this->payload())->assertForbidden();

        $me = $this->actingWith(['create_schedule_type']);
        $data = $this->postJson('/api/admin/schedule-types', $this->payload(['primary' => true]))
            ->assertOk()->json('data');

        $this->assertSame('Walks', $data['name']);
        $this->assertSame('08:00', $data['min_time']);
        $this->assertSame('20:00', $data['max_time']);
        $this->assertFalse($data['primary']);
        $this->assertFalse((bool) ScheduleType::find($data['id'])->primary);

        $audit = Audit::where('auditable_type', ScheduleType::class)->where('auditable_id', $data['id'])->sole();
        $this->assertSame('created', $audit->event);
        $this->assertSame($me->id, $audit->user_id);
    }

    public function test_update_needs_permission_and_is_audited(): void
    {
        $type = $this->type();

        $this->actingWith(['view_any_schedule_type']);
        $this->putJson("/api/admin/schedule-types/{$type->id}", $this->payload())->assertForbidden();

        $me = $this->actingWith(['update_schedule_type']);
        $this->putJson("/api/admin/schedule-types/{$type->id}", $this->payload(['name' => 'Renamed', 'primary' => true]))
            ->assertOk()->assertJsonPath('data.name', 'Renamed');

        $this->assertFalse((bool) $type->fresh()->primary);
        $audit = Audit::where('auditable_type', ScheduleType::class)->where('auditable_id', $type->id)
            ->where('event', 'updated')->sole();
        $this->assertSame($me->id, $audit->user_id);
        $this->assertSame('Renamed', $audit->new_values['name']);
    }

    public function test_a_type_with_schedules_cannot_be_deleted(): void
    {
        $type = $this->type();
        $this->scheduleOf($type);

        $this->actingWith(['delete_schedule_type']);
        $this->deleteJson("/api/admin/schedule-types/{$type->id}")
            ->assertStatus(422)->assertJsonPath('code', 'has_schedules');

        $this->assertNotNull($type->fresh());
    }

    public function test_only_active_schedules_block_deleting(): void
    {
        $type = $this->type();
        $this->scheduleOf($type)->delete(); // soft deleted

        $this->actingWith(['view_any_schedule_type']);
        $this->deleteJson("/api/admin/schedule-types/{$type->id}")->assertForbidden();

        $me = $this->actingWith(['delete_schedule_type']);
        $this->deleteJson("/api/admin/schedule-types/{$type->id}")->assertOk();

        $this->assertNull($type->fresh());
        $audit = Audit::where('auditable_type', ScheduleType::class)->where('auditable_id', $type->id)
            ->where('event', 'deleted')->sole();
        $this->assertSame($me->id, $audit->user_id);
    }
}
