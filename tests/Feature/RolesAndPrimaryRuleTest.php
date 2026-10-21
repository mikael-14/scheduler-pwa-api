<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Widgets\PendingSchedulesWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\ParticipationRequested;
use App\Notifications\ScheduleJoinedOrLeft;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The suggested roles, built from permissions only, and the approval rule of the primary type
 * (App\Services\PrimaryScheduleRule).
 */
class RolesAndPrimaryRuleTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules';

    private const VOLUNTEER = [
        'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule',
        'participate_schedule', 'cancel_participation_schedule',
    ];

    private const ADMIN = [
        'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'view_inactive_schedule',
        'create_any_schedule', 'update_any_schedule', 'approve_schedule', 'independent_schedule',
        'participate_schedule', 'cancel_participation_schedule',
    ];

    private function person(string $role, array $permissions, string $name): User
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->syncPermissions($permissions);

        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => $name]);
        $user->assignRole($role);

        return $user;
    }

    private function novo(string $name = 'Nuno Novo'): User
    {
        return $this->person('voluntario_novo', self::VOLUNTEER, $name);
    }

    private function experiente(string $name = 'Eva Experiente'): User
    {
        return $this->person('voluntario_experiente', [...self::VOLUNTEER, 'independent_schedule'], $name);
    }

    private function externo(): User
    {
        return $this->person('externo', ['view_any_schedule', 'view_schedule', 'participate_schedule', 'cancel_participation_schedule'], 'Xavier Externo');
    }

    private function admin(): User
    {
        return $this->person('admin', self::ADMIN, 'Ana Admin');
    }

    private function type(bool $primary = true, string $name = 'Abrigo'): ScheduleType
    {
        return ScheduleType::create([
            'name' => $name, 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => $primary,
        ]);
    }

    /** Stored without the model hooks: the starting situation of a test, not what it checks. */
    private function schedule(User $owner, ScheduleType $type, string $from, ?string $to, ScheduleStatus $status = ScheduleStatus::Pending, int $daysAhead = 3, bool $allDay = false): Schedule
    {
        $day = now()->addDays($daysAhead)->format('Y-m-d');
        $schedule = new Schedule([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => $status,
            'start' => "$day $from", 'end' => $to ? "$day $to" : null, 'all_day' => $allDay,
        ]);
        $schedule->saveQuietly();

        return $schedule;
    }

    // ---- the primary rule

    public function test_a_new_volunteer_alone_stays_pending_and_cannot_be_approved(): void
    {
        $type = $this->type();
        $pending = $this->schedule($this->novo(), $type, '10:00', '12:00');

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$pending->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', fn($message) => str_contains($message, 'Nuno Novo'));
        $this->assertSame(ScheduleStatus::Pending, $pending->fresh()->status);

        // approvers see why, before trying
        $row = collect($this->getJson(self::URL . '/pending')->json('data'))->firstWhere('id', $pending->id);
        $this->assertNotNull($row['approval_problem']);
        $this->assertNotNull($this->getJson(self::URL . "/{$pending->id}")->json('data.approval_problem'));
    }

    public function test_approved_once_someone_who_may_go_alone_is_approved_at_overlapping_hours(): void
    {
        $type = $this->type();
        $pending = $this->schedule($this->novo(), $type, '10:00', '12:00');
        $this->schedule($this->experiente(), $type, '09:30', '13:00', ScheduleStatus::Approved);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$pending->id}/approve")->assertOk();
        $this->assertSame(ScheduleStatus::Approved, $pending->fresh()->status);
    }

    public function test_the_responsible_must_be_there_when_the_new_volunteer_arrives(): void
    {
        $type = $this->type();
        $admin = $this->admin();
        // Experiente at 9h with no end: counts for one hour, so up to 10h
        $this->schedule($this->experiente(), $type, '09:00', null, ScheduleStatus::Approved);
        $at10 = $this->schedule($this->novo(), $type, '10:00', '12:00');
        $at1030 = $this->schedule($this->novo('Nina Nova'), $type, '10:30', '12:00');
        $at0830 = $this->schedule($this->novo('Olga Nova'), $type, '08:30', '12:00'); // alone before 9h

        Sanctum::actingAs($admin);

        $this->postJson(self::URL . "/{$at10->id}/approve")->assertOk();
        $this->postJson(self::URL . "/{$at1030->id}/approve")->assertStatus(422);
        $this->postJson(self::URL . "/{$at0830->id}/approve")->assertStatus(422);
    }

    public function test_a_new_volunteer_before_the_responsible_is_not_covered_even_when_hours_overlap(): void
    {
        $type = $this->type();
        $pending = $this->schedule($this->novo(), $type, '09:00', '12:00');
        $this->schedule($this->experiente(), $type, '10:00', '13:00', ScheduleStatus::Approved);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$pending->id}/approve")->assertStatus(422);
    }

    public function test_when_the_responsible_cancels_a_later_arrival_within_the_hour_goes_back_to_pending(): void
    {
        $type = $this->type();
        $eva = $this->schedule($this->experiente(), $type, '09:00', null, ScheduleStatus::Approved);
        $novo = $this->schedule($this->novo(), $type, '10:00', '11:00', ScheduleStatus::Approved);

        $this->actingAs($this->admin());
        $eva->update(['status' => ScheduleStatus::Cancelled]);

        $this->assertSame(ScheduleStatus::Pending, $novo->fresh()->status);
    }

    // ---- whole-day schedules

    private function allDayType(bool $primary = true): ScheduleType
    {
        return ScheduleType::create([
            'name' => 'Abrigo', 'color' => '#112233', 'status' => true, 'range' => false, 'all_day' => true, 'primary' => $primary,
        ]);
    }

    private function allDayPerson(string $name = 'Arlete'): User
    {
        return $this->person('experiente_dia_inteiro', [...self::VOLUNTEER, 'independent_schedule', 'all_day_schedule'], $name);
    }

    public function test_only_people_with_the_permission_book_whole_days(): void
    {
        $type = $this->allDayType();
        $day = now()->addDays(3)->format('Y-m-d');
        $booking = ['schedule_type_id' => $type->id, 'start' => "$day 00:00", 'all_day' => true];

        Sanctum::actingAs($this->experiente());
        $this->postJson(self::URL, $booking)->assertStatus(422)->assertJsonValidationErrors('all_day');

        Sanctum::actingAs($this->allDayPerson());
        $this->postJson(self::URL, $booking)->assertOk();

        // nor for someone else who may not (a Novo), even by someone who may
        $novo = $this->novo();
        Sanctum::actingAs($this->person('admin_dia', [...self::ADMIN, 'all_day_schedule'], 'Rita Admin'));
        $this->postJson(self::URL, $booking + ['user_id' => $novo->id])->assertStatus(422)->assertJsonValidationErrors('all_day');

        // the PWA only offers the switch to people who may
        $this->assertTrue($this->getJson(self::URL . '/form-options')->json('data.can.all_day'));
        Sanctum::actingAs($novo);
        $this->assertFalse($this->getJson(self::URL . '/form-options')->json('data.can.all_day'));
    }

    public function test_an_approved_whole_day_takes_the_day_and_the_other_way_round(): void
    {
        $type = $this->allDayType();
        $arlete = $this->schedule($this->allDayPerson(), $type, '00:00', null, ScheduleStatus::Approved, allDay: true);
        $timed = $this->schedule($this->experiente(), $type, '10:00', null);
        $otherDay = $this->schedule($this->experiente('Rui'), $type, '10:00', null, daysAhead: 4);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$timed->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', fn($message) => str_contains($message, 'Arlete'));
        $this->postJson(self::URL . "/{$otherDay->id}/approve")->assertOk();

        // a second whole day the next day cannot be approved: Rui is approved there
        $second = $this->schedule($this->allDayPerson('Bia'), $type, '00:00', null, daysAhead: 4, allDay: true);
        $this->postJson(self::URL . "/{$second->id}/approve")->assertStatus(422);
        $row = collect($this->getJson(self::URL . '/pending')->json('data'))->firstWhere('id', $second->id);
        $this->assertNotNull($row['approval_problem']);

        // once Arlete's day is cancelled, the timed one can be approved
        $arlete->update(['status' => ScheduleStatus::Cancelled]);
        $this->postJson(self::URL . "/{$timed->id}/approve")->assertOk();
    }

    public function test_the_whole_day_rule_is_per_type_and_applies_to_any_type(): void
    {
        $walks = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => false, 'all_day' => true, 'primary' => false]);
        $shelter = $this->allDayType();
        $this->schedule($this->allDayPerson(), $walks, '00:00', null, ScheduleStatus::Approved, allDay: true);
        $sameType = $this->schedule($this->novo(), $walks, '10:00', null);
        $otherType = $this->schedule($this->experiente(), $shelter, '10:00', null);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$sameType->id}/approve")->assertStatus(422);
        $this->postJson(self::URL . "/{$otherType->id}/approve")->assertOk();
    }

    public function test_a_whole_day_covers_no_new_volunteer_and_none_goes_on_one(): void
    {
        $type = $this->allDayType();
        $novo = $this->novo();
        $day = $this->schedule($this->allDayPerson(), $type, '00:00', null, allDay: true);
        $day->schedule_users()->create(['user_id' => $novo->id, 'status' => ScheduleStatus::Pending]);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$day->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', fn($message) => str_contains($message, 'Nuno Novo'));
    }

    public function test_same_day_but_other_hours_is_not_enough(): void
    {
        $type = $this->type();
        $pending = $this->schedule($this->novo(), $type, '10:00', '12:00');
        $this->schedule($this->experiente(), $type, '14:00', '16:00', ScheduleStatus::Approved);
        // pending does not count either
        $this->schedule($this->experiente('Paula Pendente'), $type, '10:00', '12:00');

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$pending->id}/approve")->assertStatus(422);
    }

    public function test_an_approved_participant_who_may_go_alone_counts_too(): void
    {
        $type = $this->type();
        $pending = $this->schedule($this->novo(), $type, '10:00', '12:00');
        $other = $this->schedule($this->novo('Nina Nova'), $type, '09:00', '13:00', ScheduleStatus::Approved);
        $other->schedule_users()->create(['user_id' => $this->experiente()->id, 'status' => ScheduleStatus::Approved]);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$pending->id}/approve")->assertOk();
    }

    public function test_booking_together_with_someone_who_may_go_alone_is_approved_in_one_go(): void
    {
        $type = $this->type();
        $novo = $this->novo();
        $eva = $this->experiente();

        // created by the volunteer with Eva on it; both pending
        Sanctum::actingAs($novo);
        $id = $this->postJson(self::URL, [
            'schedule_type_id' => $type->id,
            'start' => now()->addDays(3)->format('Y-m-d') . ' 10:00',
            'end' => now()->addDays(3)->format('Y-m-d') . ' 12:00',
            'schedule_users' => [['user_id' => $eva->id]],
        ])->assertOk()->json('data.id');

        Sanctum::actingAs($this->admin());
        $this->postJson(self::URL . "/$id/approve")->assertOk();
        $this->assertSame(ScheduleStatus::Approved, Schedule::find($id)->schedule_users()->first()->status);
    }

    public function test_other_types_and_people_who_may_go_alone_are_not_affected(): void
    {
        $walk = $this->schedule($this->novo(), $this->type(false, 'Passeios'), '10:00', '12:00');
        $eva = $this->schedule($this->experiente(), $this->type(), '10:00', '12:00');

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$walk->id}/approve")->assertOk();
        $this->postJson(self::URL . "/{$eva->id}/approve")->assertOk();
    }

    public function test_setting_the_status_directly_obeys_the_rule_too(): void
    {
        $pending = $this->schedule($this->novo(), $this->type(), '10:00', '12:00');

        $this->actingAs($this->admin());
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $pending->update(['status' => ScheduleStatus::Approved]);
    }

    #[RunInSeparateProcess]
    public function test_the_admin_panel_shows_the_reason_and_keeps_it_pending(): void
    {
        Filament::setCurrentPanel('admin');
        $pending = $this->schedule($this->novo(), $this->type(), '10:00', '12:00');
        $this->actingAs($this->admin());

        Livewire::test(PendingSchedulesWidget::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('approve')->table($pending));

        $this->assertSame(ScheduleStatus::Pending, $pending->fresh()->status);
    }

    // ---- losing the person who may go alone

    public function test_when_the_only_responsible_cancels_the_new_volunteer_goes_back_to_pending(): void
    {
        Notification::fake();
        $type = $this->type();
        $novo = $this->novo();
        $eva = $this->experiente();
        $evaSchedule = $this->schedule($eva, $type, '09:00', '13:00', ScheduleStatus::Approved);
        $novoSchedule = $this->schedule($novo, $type, '10:00', '12:00', ScheduleStatus::Approved);
        $otherHours = $this->schedule($this->novo('Nina Nova'), $type, '15:00', '16:00', ScheduleStatus::Approved);
        $admin = $this->admin();

        // Eva cancels her own schedule (as the PWA does)
        Sanctum::actingAs($eva);
        $this->patchJson(self::URL . "/{$evaSchedule->id}", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(ScheduleStatus::Pending, $novoSchedule->fresh()->status);
        // other hours never relied on Eva: untouched
        $this->assertSame(ScheduleStatus::Approved, $otherHours->fresh()->status);
        Notification::assertSentTo($novo, \App\Notifications\ScheduleBackToPending::class);
        Notification::assertSentTo($admin, \App\Notifications\SchedulePendingApproval::class);
    }

    public function test_it_stays_approved_while_someone_else_still_covers_the_hours(): void
    {
        $type = $this->type();
        $evaSchedule = $this->schedule($this->experiente(), $type, '09:00', '13:00', ScheduleStatus::Approved);
        $this->schedule($this->experiente('Rui Responsavel'), $type, '10:00', '11:00', ScheduleStatus::Approved);
        $novoSchedule = $this->schedule($this->novo(), $type, '10:00', '12:00', ScheduleStatus::Approved);

        $this->actingAs($this->admin());
        $evaSchedule->delete();

        $this->assertSame(ScheduleStatus::Approved, $novoSchedule->fresh()->status);
    }

    public function test_when_the_responsible_participant_leaves_the_schedule_goes_back_to_pending(): void
    {
        $type = $this->type();
        $eva = $this->experiente();
        $novoSchedule = $this->schedule($this->novo(), $type, '10:00', '12:00', ScheduleStatus::Approved);
        $novoSchedule->schedule_users()->create(['user_id' => $eva->id, 'status' => ScheduleStatus::Approved]);

        Sanctum::actingAs($eva);
        $this->deleteJson(self::URL . "/{$novoSchedule->id}/participation")->assertOk();

        $this->assertSame(ScheduleStatus::Pending, $novoSchedule->fresh()->status);
    }

    // ---- joining and leaving

    public function test_joining_creates_a_pending_participation_that_is_approved_with_the_rule(): void
    {
        Notification::fake();
        $type = $this->type();
        $eva = $this->experiente();
        $approved = $this->schedule($eva, $type, '10:00', '12:00', ScheduleStatus::Approved);
        $novo = $this->novo();
        $admin = $this->admin();

        Sanctum::actingAs($novo);
        $this->postJson(self::URL . "/{$approved->id}/participation")
            ->assertOk()
            ->assertJsonPath('data.can.leave', true)
            ->assertJsonPath('data.can.join', false);

        $this->assertSame(ScheduleStatus::Pending, $approved->schedule_users()->where('user_id', $novo->id)->value('status'));
        Notification::assertSentTo($admin, ParticipationRequested::class);
        Notification::assertSentTo($eva, ScheduleJoinedOrLeft::class);

        // in the approver's list, and approvable: Eva (may go alone) owns the approved schedule
        Sanctum::actingAs($admin);
        $row = collect($this->getJson(self::URL . '/pending')->json('participations'))->firstWhere('schedule_id', $approved->id);
        $this->assertSame($novo->id, $row['user']['id']);
        $this->assertNull($row['approval_problem']);

        $this->postJson(self::URL . "/{$approved->id}/participants/{$novo->id}/approve")->assertOk();
        $this->assertSame(ScheduleStatus::Approved, $approved->schedule_users()->where('user_id', $novo->id)->value('status'));
    }

    public function test_a_new_volunteer_joining_another_new_volunteer_cannot_be_approved(): void
    {
        $type = $this->type();
        // e.g. approved before the rule existed
        $approved = $this->schedule($this->novo('Nina Nova'), $type, '10:00', '12:00', ScheduleStatus::Approved);
        $novo = $this->novo();
        $approved->schedule_users()->create(['user_id' => $novo->id, 'status' => ScheduleStatus::Pending]);

        Sanctum::actingAs($this->admin());

        $this->postJson(self::URL . "/{$approved->id}/participants/{$novo->id}/approve")->assertStatus(422);
        $this->postJson(self::URL . "/{$approved->id}/participants/{$novo->id}/reject")->assertOk();
    }

    public function test_external_people_only_join_and_leave(): void
    {
        $type = $this->type(false, 'Passeios');
        $approved = $this->schedule($this->experiente(), $type, '10:00', '12:00', ScheduleStatus::Approved);
        $externo = $this->externo();

        Sanctum::actingAs($externo);

        $this->postJson(self::URL, [
            'schedule_type_id' => $type->id,
            'start' => now()->addDays(3)->format('Y-m-d') . ' 10:00',
            'end' => now()->addDays(3)->format('Y-m-d') . ' 12:00',
        ])->assertForbidden();

        $this->postJson(self::URL . "/{$approved->id}/participation")->assertOk();
        $this->postJson(self::URL . "/{$approved->id}/participation")->assertStatus(422); // already in
        $this->deleteJson(self::URL . "/{$approved->id}/participation")->assertOk();
        $this->assertSame(0, $approved->schedule_users()->count());
    }

    public function test_past_schedules_cannot_be_joined_left_or_edited_without_the_permission(): void
    {
        $type = $this->type(false, 'Passeios');
        $novo = $this->novo();
        $past = $this->schedule($this->experiente(), $type, '10:00', '12:00', ScheduleStatus::Approved, daysAhead: -2);
        $mine = $this->schedule($novo, $type, '10:00', '12:00', ScheduleStatus::Pending, daysAhead: -2);
        $past->schedule_users()->create(['user_id' => $this->externo()->id, 'status' => ScheduleStatus::Approved]);

        Sanctum::actingAs($novo);
        $this->postJson(self::URL . "/{$past->id}/participation")->assertStatus(422);
        $this->patchJson(self::URL . "/{$mine->id}", ['description' => 'late edit'])->assertForbidden();
        $this->assertFalse($this->getJson(self::URL . "/{$mine->id}")->json('data.can.update'));

        Sanctum::actingAs($past->schedule_users()->first()->user);
        $this->deleteJson(self::URL . "/{$past->id}/participation")->assertStatus(422);
    }

    // ---- owner and participant

    public function test_the_owner_is_never_also_a_participant(): void
    {
        $type = $this->type(false, 'Passeios');
        $novo = $this->novo();
        $eva = $this->experiente();
        $schedule = $this->schedule($novo, $type, '10:00', '12:00', ScheduleStatus::Approved);

        $this->actingAs($this->admin());

        // nothing can add the owner as a participant
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $schedule->schedule_users()->create(['user_id' => $novo->id, 'status' => ScheduleStatus::Pending]);
        } finally {
            // handed over to a participant: they stop being a participant
            $schedule->schedule_users()->create(['user_id' => $eva->id, 'status' => ScheduleStatus::Approved]);
            $schedule->update(['user_id' => $eva->id]);
            $this->assertSame(0, $schedule->schedule_users()->where('user_id', $eva->id)->count());
        }
    }

    // ---- what the agenda shows

    public function test_volunteers_see_pending_approved_and_their_own_while_admins_see_everything(): void
    {
        $type = $this->type(false, 'Passeios');
        $novo = $this->novo();
        $other = $this->experiente();
        $visible = $this->schedule($other, $type, '08:00', '09:00', ScheduleStatus::Approved);
        $hidden = $this->schedule($other, $type, '10:00', '11:00', ScheduleStatus::Rejected);
        $mineRejected = $this->schedule($novo, $type, '12:00', '13:00', ScheduleStatus::Rejected);
        $joinedCancelled = $this->schedule($other, $type, '14:00', '15:00', ScheduleStatus::Cancelled);
        $joinedCancelled->schedule_users()->create(['user_id' => $novo->id, 'status' => ScheduleStatus::Cancelled]);

        Sanctum::actingAs($novo);
        $ids = collect($this->getJson(self::URL . '?per_page=100')->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$visible->id, $mineRejected->id, $joinedCancelled->id], $ids->all());
        $this->getJson(self::URL . "/{$hidden->id}")->assertNotFound();

        Sanctum::actingAs($this->admin());
        $this->assertCount(4, $this->getJson(self::URL . '?per_page=100')->json('data'));
    }
}
