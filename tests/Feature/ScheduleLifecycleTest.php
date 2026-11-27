<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * How a schedule ends in the apps: its creator cancels it before it starts, approvers approve or
 * reject it, an admin marks a no-show (the owner does it for participants), and schedules:complete
 * marks it completed. Nobody deletes or completes one by hand.
 */
class ScheduleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules';

    private const MEMBER = ['view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule'];

    private const ADMIN = [...self::MEMBER, 'approve_schedule', 'update_status_schedule', 'update_any_schedule', 'update_past_schedule'];

    private function user(array $permissions): User
    {
        static $n = 0;
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::create(['name' => 'lifecycle-' . ++$n, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    private function schedule(User $owner, ScheduleStatus $status, string $start, ?string $end = null, bool $allDay = false): Schedule
    {
        $type = ScheduleType::firstOrCreate(['name' => 'Cleaning'], [
            'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => true,
        ]);

        // past ones too: the "no past schedules" rule is for people booking, not for these fixtures
        return Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => $status,
            'start' => $start, 'end' => $end, 'all_day' => $allDay,
        ]));
    }

    private function tomorrow(): Schedule
    {
        return $this->schedule($this->owner, ScheduleStatus::Approved, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(12, 0));
    }

    private function startedAnHourAgo(ScheduleStatus $status = ScheduleStatus::Approved): Schedule
    {
        return $this->schedule($this->owner, $status, now()->subHour(), now()->addHour());
    }

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user(self::MEMBER);
    }

    public function test_the_creator_cancels_pending_or_approved_schedules_until_they_start(): void
    {
        // midday: "an hour ago" must still be today (just after midnight it would be a past day)
        $this->travelTo(now()->setTime(12, 0));
        $pending = $this->schedule($this->owner, ScheduleStatus::Pending, now()->addDay()->setTime(10, 0));
        $approved = $this->tomorrow();
        $started = $this->startedAnHourAgo();

        Sanctum::actingAs($this->owner);
        $this->assertTrue($this->getJson(self::URL . "/{$approved->id}")->json('data.can.cancel'));
        $this->assertFalse($this->getJson(self::URL . "/{$started->id}")->json('data.can.cancel'));

        $this->patchJson(self::URL . "/{$pending->id}", ['status' => 'cancelled'])->assertOk();
        $this->patchJson(self::URL . "/{$approved->id}", ['status' => 'cancelled'])->assertOk();
        $this->assertSame(ScheduleStatus::Cancelled, $approved->fresh()->status);

        // once it started it is too late (only its description can still change)
        $this->patchJson(self::URL . "/{$started->id}", ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame(ScheduleStatus::Approved, $started->fresh()->status);

        // and someone else cannot cancel it
        $other = $this->tomorrow();
        Sanctum::actingAs($this->user(self::MEMBER));
        $this->assertFalse($this->getJson(self::URL . "/{$other->id}")->json('data.can.cancel'));
        $this->patchJson(self::URL . "/{$other->id}", ['status' => 'cancelled'])->assertStatus(403);
    }

    public function test_the_owner_edits_everything_until_the_start_then_only_the_description(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $later = $this->schedule($this->owner, ScheduleStatus::Approved, now()->addHour(), now()->addHours(2));
        $started = $this->startedAnHourAgo();

        Sanctum::actingAs($this->owner);

        // not started yet (same day): everything
        $this->assertTrue($this->getJson(self::URL . "/{$later->id}")->json('data.can.update'));
        $this->patchJson(self::URL . "/{$later->id}", ['end' => now()->addHours(3)->format('Y-m-d H:i')])->assertOk();

        // started: the times, the participants, the status are fixed...
        $this->assertFalse($this->getJson(self::URL . "/{$started->id}")->json('data.can.update'));
        $this->assertTrue($this->getJson(self::URL . "/{$started->id}")->json('data.can.update_description'));
        $this->patchJson(self::URL . "/{$started->id}", ['end' => now()->addHours(3)->format('Y-m-d H:i')])
            ->assertForbidden()
            ->assertJsonPath('message', __('This schedule has already started: only its description can be changed.'));
        $this->patchJson(self::URL . "/{$started->id}", ['description' => 'x', 'schedule_users' => [['user_id' => $this->user(self::MEMBER)->id]]])
            ->assertForbidden();
        $this->assertSame(0, $started->schedule_users()->count());

        // ...but the description changes, through PATCH or PUT, and it stays approved
        $this->patchJson(self::URL . "/{$started->id}", ['description' => 'Running late'])->assertOk();
        $this->putJson(self::URL . "/{$started->id}", ['description' => 'Running very late'])->assertOk();
        $this->assertSame('Running very late', $started->fresh()->description);
        $this->assertSame(ScheduleStatus::Approved, $started->fresh()->status);
    }

    public function test_the_description_locks_once_the_schedule_is_completed_or_cancelled(): void
    {
        $completed = $this->schedule($this->owner, ScheduleStatus::Completed, now()->subDays(2), now()->subDays(2)->addHour());
        $cancelled = $this->schedule($this->owner, ScheduleStatus::Cancelled, now()->subHour(), now()->addHour());

        Sanctum::actingAs($this->owner);
        foreach ([$completed, $cancelled] as $schedule) {
            $this->assertFalse($this->getJson(self::URL . "/{$schedule->id}")->json('data.can.update_description'));
            $this->patchJson(self::URL . "/{$schedule->id}", ['description' => 'too late'])->assertForbidden();
        }
    }

    public function test_others_schedules_follow_the_same_rule_and_update_past_schedule_lifts_it(): void
    {
        $started = $this->startedAnHourAgo();
        $newEnd = now()->addHours(3)->format('Y-m-d H:i');

        // update_any_schedule without update_past_schedule: description only
        Sanctum::actingAs($this->user([...self::MEMBER, 'update_any_schedule']));
        $this->patchJson(self::URL . "/{$started->id}", ['end' => $newEnd])->assertForbidden();
        $this->patchJson(self::URL . "/{$started->id}", ['description' => 'noted'])->assertOk();

        // update_past_schedule: everything, at any time
        Sanctum::actingAs($this->user(self::ADMIN));
        $this->patchJson(self::URL . "/{$started->id}", ['end' => $newEnd])->assertOk();
        $this->assertSame($newEnd, $started->fresh()->end->format('Y-m-d H:i'));
    }

    public function test_the_start_lock_also_holds_outside_the_api(): void
    {
        $started = $this->startedAnHourAgo();
        $this->actingAs($this->owner);

        // e.g. a drag on the panel calendar
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $started->update(['end' => now()->addHours(3)]);
    }

    public function test_the_owner_still_marks_a_participant_as_not_shown_after_the_start(): void
    {
        $started = $this->startedAnHourAgo();
        $participant = $started->schedule_users()->create(['user_id' => $this->user(self::MEMBER)->id, 'status' => ScheduleStatus::Approved]);

        Sanctum::actingAs($this->owner);
        $this->assertTrue(\App\Services\ScheduleAccess::canMarkParticipantNotShown($this->owner, $started, $participant));
    }

    public function test_deleting_follows_the_start_time(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $later = $this->schedule($this->owner, ScheduleStatus::Pending, now()->addHour(), now()->addHours(2));
        $started = $this->startedAnHourAgo(ScheduleStatus::Pending);

        $this->assertTrue(\App\Services\ScheduleAccess::canDelete($this->owner, $later));
        $this->assertFalse(\App\Services\ScheduleAccess::canDelete($this->owner, $started));
        $this->assertTrue(\App\Services\ScheduleAccess::canDelete($this->user(['delete_any_schedule', 'update_past_schedule']), $started));
    }

    public function test_nobody_completes_a_schedule_by_hand(): void
    {
        $started = $this->startedAnHourAgo();

        Sanctum::actingAs($this->user(self::ADMIN));
        $this->patchJson(self::URL . "/{$started->id}", ['status' => 'completed'])->assertStatus(422);

        $statuses = array_column($this->getJson(self::URL . '/form-options')->json('data.statuses'), 'value');
        $this->assertNotContains('completed', $statuses);
    }

    public function test_an_admin_marks_a_started_schedule_as_not_shown_with_an_optional_message(): void
    {
        $started = $this->startedAnHourAgo();
        $future = $this->tomorrow();
        $admin = $this->user(self::ADMIN);

        // members cannot
        Sanctum::actingAs($this->owner);
        $this->assertFalse($this->getJson(self::URL . "/{$started->id}")->json('data.can.not_shown'));
        $this->postJson(self::URL . "/{$started->id}/not-shown")->assertForbidden();

        Sanctum::actingAs($admin);
        // not before it starts
        $this->assertFalse($this->getJson(self::URL . "/{$future->id}")->json('data.can.not_shown'));
        $this->postJson(self::URL . "/{$future->id}/not-shown")->assertStatus(422)->assertJsonPath('code', 'cannot_mark_not_shown');
        $this->patchJson(self::URL . "/{$future->id}", ['status' => 'not_shown'])->assertStatus(422);

        $this->assertTrue($this->getJson(self::URL . "/{$started->id}")->json('data.can.not_shown'));
        $otherApprover = $this->user(self::ADMIN);
        \Illuminate\Support\Facades\Notification::fake();
        $this->postJson(self::URL . "/{$started->id}/not-shown", ['message' => 'Phoned in sick'])
            ->assertOk()->assertJsonPath('data.status', 'not_shown');
        $this->assertStringEndsWith(': Phoned in sick', $started->fresh()->internal_note);

        // the owner is told, and the approvers; not the admin who marked it
        \Illuminate\Support\Facades\Notification::assertSentTo($this->owner, \App\Notifications\MarkedNotShown::class);
        \Illuminate\Support\Facades\Notification::assertSentTo($otherApprover, \App\Notifications\NotShownRecorded::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($admin, \App\Notifications\NotShownRecorded::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($this->owner, \App\Notifications\NotShownRecorded::class);

        // a pending one (nobody decided on it) is not a no-show
        $pending = $this->startedAnHourAgo(ScheduleStatus::Pending);
        $this->postJson(self::URL . "/{$pending->id}/not-shown")->assertStatus(422);
    }

    public function test_the_owner_marks_a_participant_who_did_not_come(): void
    {
        $friend = $this->user(self::MEMBER);
        $started = $this->startedAnHourAgo();
        $started->schedule_users()->create(['user_id' => $friend->id, 'status' => ScheduleStatus::Approved]);
        $future = $this->tomorrow();
        $future->schedule_users()->create(['user_id' => $friend->id, 'status' => ScheduleStatus::Approved]);

        // not the participant themselves, nor anyone else
        Sanctum::actingAs($friend);
        $this->assertFalse($this->getJson(self::URL . "/{$started->id}")->json('data.schedule_users.0.can_mark_not_shown'));
        $this->postJson(self::URL . "/{$started->id}/participants/{$friend->id}/not-shown")->assertStatus(422);

        Sanctum::actingAs($this->owner);
        // not before it starts
        $this->postJson(self::URL . "/{$future->id}/participants/{$friend->id}/not-shown")->assertStatus(422);

        $this->assertTrue($this->getJson(self::URL . "/{$started->id}")->json('data.schedule_users.0.can_mark_not_shown'));
        $approver = $this->user(self::ADMIN);
        \Illuminate\Support\Facades\Notification::fake();
        $this->postJson(self::URL . "/{$started->id}/participants/{$friend->id}/not-shown")
            ->assertOk()->assertJsonPath('data.schedule_users.0.status', 'not_shown');
        // the schedule itself keeps its status
        $this->assertSame(ScheduleStatus::Approved, $started->fresh()->status);

        // the person marked is told; the owner, who did it, is not
        \Illuminate\Support\Facades\Notification::assertSentTo($friend, \App\Notifications\MarkedNotShown::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($this->owner, \App\Notifications\MarkedNotShown::class);
        \Illuminate\Support\Facades\Notification::assertSentTo($approver, \App\Notifications\NotShownRecorded::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($friend, \App\Notifications\NotShownRecorded::class);
    }

    public function test_the_same_rules_hold_for_saves_in_the_admin_panel(): void
    {
        $admin = $this->user(self::ADMIN);
        $friend = $this->user(self::MEMBER);
        $future = $this->tomorrow();
        $started = $this->startedAnHourAgo();
        $participant = $started->schedule_users()->create(['user_id' => $friend->id, 'status' => ScheduleStatus::Approved]);

        // the panel saves the model as the signed-in person
        $this->actingAs($admin);
        $this->assertFalse(\App\Services\ScheduleAccess::mayChangeStatus($admin, $future, ScheduleStatus::Approved, ScheduleStatus::Completed));
        $this->assertThrowsStatus(fn() => $started->update(['status' => ScheduleStatus::Completed]));
        $this->assertThrowsStatus(fn() => $future->update(['status' => ScheduleStatus::NotShown]));
        $this->assertThrowsStatus(fn() => $participant->update(['status' => ScheduleStatus::Completed]));

        $started->update(['status' => ScheduleStatus::NotShown]);
        $this->assertSame(ScheduleStatus::NotShown, $started->fresh()->status);

        // a member cannot cancel someone else's schedule, nor their own once it started
        $this->actingAs($friend);
        $this->assertThrowsStatus(fn() => $future->fresh()->update(['status' => ScheduleStatus::Cancelled]));
        $this->actingAs($this->owner);
        $this->assertThrowsStatus(fn() => $this->startedAnHourAgo()->update(['status' => ScheduleStatus::Cancelled]));
        $future->fresh()->update(['status' => ScheduleStatus::Cancelled]);
        $this->assertSame(ScheduleStatus::Cancelled, $future->fresh()->status);

        // the owner marks the participant who did not come
        $participant->fresh()->update(['status' => ScheduleStatus::NotShown]);
        $this->assertSame(ScheduleStatus::NotShown, $participant->fresh()->status);
    }

    private function assertThrowsStatus(callable $save): void
    {
        try {
            $save();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());

            return;
        }

        $this->fail('The status change should have been refused.');
    }

    public function test_approved_schedules_that_are_over_become_completed(): void
    {
        $friend = $this->user(self::MEMBER);
        $over = $this->schedule($this->owner, ScheduleStatus::Approved, now()->subHours(3), now()->subHour());
        $over->schedule_users()->create(['user_id' => $friend->id, 'status' => ScheduleStatus::Approved]);
        $noEnd = $this->schedule($this->owner, ScheduleStatus::Approved, now()->subMinute());
        $running = $this->startedAnHourAgo();
        $pending = $this->schedule($this->owner, ScheduleStatus::Pending, now()->subHours(3), now()->subHour());
        $notShown = $this->schedule($this->owner, ScheduleStatus::NotShown, now()->subHours(3), now()->subHour());
        $yesterday = $this->schedule($this->owner, ScheduleStatus::Approved, now()->subDay()->startOfDay(), now()->subDay()->startOfDay(), allDay: true);
        $today = $this->schedule($this->owner, ScheduleStatus::Approved, now()->startOfDay(), now()->startOfDay(), allDay: true);

        $this->artisan('schedules:complete')->assertSuccessful();

        $this->assertSame(ScheduleStatus::Completed, $over->fresh()->status);
        $this->assertSame(ScheduleStatus::Completed, $over->schedule_users()->first()->status);
        $this->assertSame(ScheduleStatus::Completed, $noEnd->fresh()->status);
        $this->assertSame(ScheduleStatus::Completed, $yesterday->fresh()->status);
        // still going, never decided, or already marked
        $this->assertSame(ScheduleStatus::Approved, $running->fresh()->status);
        $this->assertSame(ScheduleStatus::Approved, $today->fresh()->status);
        $this->assertSame(ScheduleStatus::Pending, $pending->fresh()->status);
        $this->assertSame(ScheduleStatus::NotShown, $notShown->fresh()->status);
    }
}
