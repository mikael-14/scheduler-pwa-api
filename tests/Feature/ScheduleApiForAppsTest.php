<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** What the PWA needs from the schedule API: names, colours, abilities and the form's choices. */
class ScheduleApiForAppsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules';

    private const MEMBER = ['view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule'];

    /** The abilities these tests are about; newer ones (join, leave...) have tests of their own. */
    private const BASIC = ['update', 'cancel', 'approve'];

    private function user(array $permissions, array $attributes = []): User
    {
        static $n = 0;
        $user = User::factory()->create(array_merge(['approved_at' => now(), 'status' => true], $attributes));
        $role = Role::create(['name' => 'role-' . ++$n, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    private function type(array $attributes = []): ScheduleType
    {
        return ScheduleType::create(array_merge([
            'name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false,
            'min_time' => '08:00', 'max_time' => '18:00',
        ], $attributes));
    }

    private function schedule(User $owner, ScheduleType $type, ScheduleStatus $status = ScheduleStatus::Pending): Schedule
    {
        $day = now()->addDays(3)->format('Y-m-d');

        return Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => $status,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ]));
    }

    public function test_list_carries_names_colours_and_what_i_may_do(): void
    {
        $type = $this->type();
        $me = $this->user(self::MEMBER, ['name' => 'Me Myself']);
        $friend = $this->user(self::MEMBER, ['name' => 'Friend']);
        $mine = $this->schedule($me, $type);
        $mine->schedule_users()->create(['user_id' => $friend->id, 'status' => ScheduleStatus::Pending]);
        $theirs = $this->schedule($friend, $type, ScheduleStatus::Approved);

        Sanctum::actingAs($me);
        $rows = collect($this->getJson(self::URL)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(['id' => $type->id, 'name' => 'Cleaning', 'color' => '#112233', 'all_day' => false, 'range' => true], $rows[$mine->id]['schedule_type']);
        $this->assertSame(['id' => $me->id, 'name' => 'Me Myself', 'avatar_url' => null], $rows[$mine->id]['user']);
        $this->assertSame('Friend', $rows[$mine->id]['schedule_users'][0]['name']);

        // my pending schedule: edit, cancel; never approve my own without the permission
        $this->assertSame(['update' => true, 'cancel' => true, 'approve' => false], Arr::only($rows[$mine->id]['can'], self::BASIC));
        // someone else's: nothing
        $this->assertSame(['update' => false, 'cancel' => false, 'approve' => false], Arr::only($rows[$theirs->id]['can'], self::BASIC));
    }

    public function test_abilities_follow_the_permissions_and_the_status(): void
    {
        $type = $this->type();
        $owner = $this->user(self::MEMBER);
        $approved = $this->schedule($owner, $type, ScheduleStatus::Approved);

        // an approved schedule of mine: still cancel it (there is no delete in the apps)
        Sanctum::actingAs($owner);
        $this->assertSame(
            ['update' => true, 'cancel' => true, 'approve' => false],
            Arr::only($this->getJson(self::URL . "/{$approved->id}")->assertOk()->json('data.can'), self::BASIC)
        );

        // an approver sees approve on pending schedules only
        $approver = $this->user(['view_any_schedule', 'view_schedule', 'approve_schedule']);
        $pending = $this->schedule($owner, $type);
        Sanctum::actingAs($approver);
        $rows = collect($this->getJson(self::URL)->json('data'))->keyBy('id');
        $this->assertTrue($rows[$pending->id]['can']['approve']);
        $this->assertFalse($rows[$approved->id]['can']['approve']);
        $this->assertFalse($rows[$pending->id]['can']['update']);
    }

    public function test_list_does_not_query_once_per_row(): void
    {
        $type = $this->type();
        $me = $this->user(self::MEMBER);
        foreach (range(1, 15) as $i) {
            $schedule = $this->schedule($this->user([]), $type);
            $schedule->schedule_users()->create(['user_id' => $me->id, 'status' => ScheduleStatus::Pending]);
        }

        Sanctum::actingAs($me);
        DB::enableQueryLog();
        $this->getJson(self::URL . '?per_page=100')->assertOk()->assertJsonCount(15, 'data');

        $this->assertLessThan(25, count(DB::getQueryLog()), 'the list should eager load names and types');
    }

    public function test_form_options_give_active_types_with_rules_people_and_my_choices(): void
    {
        $this->type(['name' => 'Walks', 'all_day' => true, 'min_time' => '09:30', 'max_time' => '17:00']);
        $this->type(['name' => 'Retired', 'status' => false]);
        $me = $this->user(self::MEMBER, ['name' => 'Me']);
        $this->user([], ['name' => 'Inactive', 'status' => false]);
        $this->user([], ['name' => 'Pending', 'approved_at' => null]);

        Sanctum::actingAs($me);
        $data = $this->getJson(self::URL . '/form-options')->assertOk()->json('data');

        $this->assertSame(['Walks'], array_column($data['types'], 'name'));
        $this->assertSame('09:30', $data['types'][0]['min_time']);
        $this->assertSame('17:00', $data['types'][0]['max_time']);
        $this->assertTrue($data['types'][0]['all_day']);
        $this->assertSame(['Me'], array_column($data['people'], 'name'));
        // a member may only leave it pending or cancel it
        $this->assertSame(['pending', 'cancelled'], array_column($data['statuses'], 'value'));
        $this->assertSame(['book_for_others' => false, 'past_dates' => false, 'all_day' => false, 'internal_note' => false], $data['can']);
        $this->assertSame(config('app.timezone'), $data['timezone']);
    }

    public function test_times_are_lisbon_time_and_come_back_as_the_same_moment(): void
    {
        $this->assertSame('Europe/Lisbon', config('app.timezone'));

        $type = $this->type(['min_time' => '08:00', 'max_time' => '18:00']);
        Sanctum::actingAs($this->user(self::MEMBER));

        // what the PWA sends: wall-clock time, no timezone (summer: Lisbon is UTC+1)
        $year = now()->year + 1;
        $created = $this->postJson(self::URL, [
            'schedule_type_id' => $type->id,
            'start' => "$year-07-15 08:30",
            'end' => "$year-07-15 10:00",
        ])->assertSuccessful()->json('data');

        // within the type's 08:00-18:00 in Lisbon time, and returned as the same moment in UTC
        $this->assertSame("$year-07-15T07:30:00.000000Z", $created['start']);
        $this->assertSame("$year-07-15T09:00:00.000000Z", $created['end']);

        // the type's window (start / end) is Lisbon time too: 09:00 Lisbon on the last day is
        // after a window ending at 08:00 Lisbon, even though it is 08:00 UTC
        $type->update(['end' => "$year-07-20 08:00"]);
        $this->postJson(self::URL, [
            'schedule_type_id' => $type->id,
            'start' => "$year-07-20 07:00",
            'end' => "$year-07-20 09:00",
        ])->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_calendar_shows_the_hours_of_all_active_types_and_never_refuses_by_hour(): void
    {
        $this->type(['name' => 'Walks', 'min_time' => '09:00', 'max_time' => '17:00']);
        $this->type(['name' => 'Night', 'min_time' => '07:30', 'max_time' => '00:00']); // until midnight
        $this->type(['name' => 'Retired', 'status' => false, 'min_time' => '01:00', 'max_time' => '02:00']);
        Sanctum::actingAs($this->user(['view_any_schedule']));

        $this->getJson(self::URL . '/calendar-options')->assertOk()
            ->assertJsonPath('data.hours', ['min' => '07:30:00', 'max' => '24:00:00'])
            ->assertJsonPath('data.timezone', 'Europe/Lisbon');

        // a type without hours: the calendar shows the whole day
        $this->type(['name' => 'Any time', 'min_time' => null, 'max_time' => null]);
        $this->getJson(self::URL . '/calendar-options')->assertOk()
            ->assertJsonPath('data.hours', ['min' => null, 'max' => null]);
    }

    public function test_calendar_filters_by_type_status_and_person(): void
    {
        $cleaning = $this->type();
        $walks = $this->type(['name' => 'Walks', 'color' => '#445566']);
        $retired = $this->type(['name' => 'Retired', 'status' => false]);
        $ana = $this->user(self::MEMBER, ['name' => 'Ana']);
        $rui = $this->user(self::MEMBER, ['name' => 'Rui']);
        $eva = $this->user(self::MEMBER, ['name' => 'Eva']);
        $anaCleaning = $this->schedule($ana, $cleaning);
        $ruiWalks = $this->schedule($rui, $walks, ScheduleStatus::Approved);
        $ruiWalks->schedule_users()->create(['user_id' => $eva->id, 'status' => ScheduleStatus::Approved]);
        $evaRetired = $this->schedule($eva, $retired, ScheduleStatus::Approved);

        Sanctum::actingAs($ana);
        $ids = fn(array $filter) => collect($this->getJson(self::URL . '?' . http_build_query(['filter' => $filter]))
            ->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$anaCleaning->id, $ruiWalks->id], $ids(['schedule_type_id' => "{$cleaning->id},{$walks->id}"]));
        $this->assertSame([$ruiWalks->id, $evaRetired->id], $ids(['status' => 'approved']));
        // a person: what they own and what they take part in
        $this->assertSame([$ruiWalks->id, $evaRetired->id], $ids(['person' => (string) $eva->id]));
        $this->assertSame([$anaCleaning->id, $ruiWalks->id], $ids(['person' => "{$ana->id},{$rui->id}"]));
        // together they narrow down
        $this->assertSame([$ruiWalks->id], $ids(['person' => (string) $eva->id, 'schedule_type_id' => (string) $walks->id]));

        $filters = $this->getJson(self::URL . '/calendar-options')->assertOk()->json('data.filters');
        // every type, also those switched off (older schedules use them)
        $this->assertSame(['Cleaning', 'Retired', 'Walks'], array_column($filters['types'], 'name'));
        $this->assertSame(['Ana', 'Eva', 'Rui'], array_column($filters['people'], 'name'));
        $this->assertSame(array_map(fn($s) => $s->value, ScheduleStatus::cases()), array_column($filters['statuses'], 'value'));
        // others' cancelled, rejected... schedules are not shown to members: those only find their own
        $everyone = array_column($filters['statuses'], 'everyone', 'value');
        $this->assertTrue($everyone['approved']);
        $this->assertFalse($everyone['cancelled']);

        Sanctum::actingAs($this->user([...self::MEMBER, 'view_inactive_schedule']));
        $everyone = array_column($this->getJson(self::URL . '/calendar-options')->json('data.filters.statuses'), 'everyone', 'value');
        $this->assertTrue($everyone['cancelled']);
    }

    public function test_admin_panel_calendar_shows_the_same_hours(): void
    {
        $walks = $this->type(['name' => 'Walks', 'min_time' => '09:00', 'max_time' => '17:00']);
        $this->type(['name' => 'Early', 'min_time' => '07:00', 'max_time' => '12:00']);

        // central calendar: all active types
        $central = new \App\Livewire\CalendarWidget();
        $this->assertSame(['07:00:00', '17:00:00'], [$central->config()['slotMinTime'], $central->config()['slotMaxTime']]);

        // a type's own page: its own hours
        $typePage = new \App\Livewire\CalendarWidget();
        $typePage->record = $walks;
        $this->assertSame(['09:00:00', '17:00:00'], [$typePage->config()['slotMinTime'], $typePage->config()['slotMaxTime']]);
    }

    public function test_type_rules_service_matches_the_type(): void
    {
        $type = $this->type([
            'range' => false, 'all_day' => true, 'min_time' => '08:00', 'max_time' => '00:00',
            'start' => '2030-01-01 09:00', 'end' => '2030-12-31 18:00',
        ]);
        $rules = \App\Services\ScheduleTypeRules::for($type->id);

        $this->assertFalse($rules->requiresEnd());
        $this->assertTrue($rules->allowsAllDay());
        $this->assertTrue($rules->isActive());
        $this->assertSame('2030-01-01 09:00', $rules->windowStart()->format('Y-m-d H:i'));
        $this->assertSame('2030-12-31 18:00', $rules->windowEnd()->format('Y-m-d H:i'));
        $this->assertSame(['min' => '08:00:00', 'max' => '24:00:00'], $rules->calendarHours());

        // a changed type is read again, not served from the per-request cache
        $type->update(['all_day' => false]);
        $this->assertFalse(\App\Services\ScheduleTypeRules::for($type->id)->allowsAllDay());
    }

    public function test_form_options_need_a_schedule_writing_permission(): void
    {
        Sanctum::actingAs($this->user(['view_any_schedule']));
        $this->getJson(self::URL . '/form-options')->assertForbidden();
    }
}
