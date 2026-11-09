<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ViewSchedule;
use App\Filament\Resources\Schedules\RelationManagers\CancelledParticipantsRelationManager;
use App\Livewire\CalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\ScheduleUser;
use App\Models\User;
use App\Notifications\ScheduleJoinedOrLeft;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cancelling a participation keeps the person on the schedule as cancelled (ScheduleUser soft
 * deletes into cancelled_at): only before it starts, never counted, and they can join again.
 */
class ParticipantCancellationTest extends TestCase
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
        Notification::fake();
    }

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

    private function volunteer(string $name): User
    {
        return $this->person('volunteer', self::VOLUNTEER, $name);
    }

    private function admin(): User
    {
        return $this->person('admin', [
            ...self::VOLUNTEER, 'view_inactive_schedule', 'create_any_schedule', 'update_any_schedule',
            'approve_schedule', 'independent_schedule',
        ], 'Ana Admin');
    }

    /** Stored without the model hooks: the starting situation of a test. */
    private function schedule(User $owner, ScheduleStatus $status = ScheduleStatus::Approved, ?\Carbon\CarbonInterface $start = null): Schedule
    {
        $type = ScheduleType::firstOrCreate(['name' => 'Passeios'], ['color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => false]);
        $start ??= now()->addDays(3)->setTime(10, 0);
        $schedule = new Schedule([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => $status,
            'start' => $start, 'end' => $start->copy()->addHours(2),
        ]);
        $schedule->saveQuietly();

        return $schedule;
    }

    public function test_cancelling_keeps_the_person_as_cancelled_and_they_can_join_again(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $maria = $this->volunteer('Maria');
        $schedule = $this->schedule($owner);

        Sanctum::actingAs($maria);
        $this->postJson(self::URL . "/{$schedule->id}/participation")->assertOk();
        $rowId = ScheduleUser::where('schedule_id', $schedule->id)->value('id');
        // the owner's email says it in a full sentence
        Notification::assertSentTo($owner, ScheduleJoinedOrLeft::class, function (ScheduleJoinedOrLeft $notification) use ($owner) {
            $mail = $notification->toMail($owner);

            return $notification->joined
                && str_starts_with($mail->introLines[0], 'Maria juntou-se ao seu agendamento de Passeios, a ')
                && $mail->actionText === 'Abrir o agendamento'
                // in the app and on the phone: the same sentence
                && $notification->toDatabase($owner)['body'] === $mail->introLines[0];
        });

        $data = $this->deleteJson(self::URL . "/{$schedule->id}/participation")->assertOk()->json('data');

        // still there, as cancelled, and not among the people going
        $row = ScheduleUser::withTrashed()->find($rowId);
        $this->assertNotNull($row->cancelled_at);
        $this->assertSame(ScheduleStatus::Cancelled, $row->status);
        $this->assertSame([], $data['schedule_users']);
        $this->assertSame('Maria', $data['cancelled_users'][0]['name']);
        $this->assertTrue($data['can']['join']);
        $this->assertFalse($data['can']['leave']);
        $this->assertSame(0, $schedule->schedule_users()->count());

        // still in "my schedules", so it can be found again
        $mine = collect($this->getJson(self::URL . '?filter[mine]=1')->json('data'))->pluck('id');
        $this->assertContains($schedule->id, $mine);

        // joining again: the same row, with the schedule's status at that moment
        $data = $this->postJson(self::URL . "/{$schedule->id}/participation")->assertOk()->json('data');
        $row->refresh();
        $this->assertNull($row->cancelled_at);
        $this->assertSame(ScheduleStatus::Approved, $row->status);
        $this->assertSame($rowId, $data['schedule_users'][0]['id']);
        $this->assertSame([], $data['cancelled_users']);
    }

    public function test_cancelling_and_joining_again_only_before_it_starts(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $maria = $this->volunteer('Maria');
        $started = $this->schedule($owner, start: now()->subMinutes(30));
        ScheduleUser::withoutEvents(fn() => ScheduleUser::create(['schedule_id' => $started->id, 'user_id' => $maria->id, 'status' => ScheduleStatus::Approved]));

        Sanctum::actingAs($maria);
        $this->assertFalse($this->getJson(self::URL . "/{$started->id}")->json('data.can.leave'));
        $this->deleteJson(self::URL . "/{$started->id}/participation")->assertStatus(422)->assertJsonPath('code', 'cannot_leave');

        // cancelled earlier: no joining again once it started
        $started->schedule_users()->first()->delete();
        $this->assertFalse($this->getJson(self::URL . "/{$started->id}")->json('data.can.join'));
        $this->postJson(self::URL . "/{$started->id}/participation")->assertStatus(422);
    }

    public function test_the_people_on_it_are_told_and_a_decision_never_brings_them_back(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $maria = $this->volunteer('Maria');
        $schedule = $this->schedule($owner, ScheduleStatus::Pending);
        $schedule->schedule_users()->create(['user_id' => $maria->id]);

        $this->actingAs($maria);
        \App\Services\ScheduleParticipation::leave($maria, $schedule);
        // in the app and by email: she cancelled her attendance (in full sentences by email)
        Notification::assertSentTo($owner, ScheduleJoinedOrLeft::class, function (ScheduleJoinedOrLeft $notification) use ($owner) {
            $mail = $notification->toMail($owner);

            return $notification->toDatabase($owner)['title'] === 'Maria cancelou a presença no seu agendamento'
                && $mail->subject === 'Maria cancelou a presença no seu agendamento'
                && str_starts_with($mail->introLines[0], 'Maria cancelou a presença no seu agendamento de Passeios, a ')
                && str_contains($mail->introLines[1], 'Pode voltar a participar até à hora de início.')
                && $mail->actionText === 'Abrir o agendamento'
                && $notification->toDatabase($owner)['body'] === $mail->introLines[0];
        });

        // approving (or anything else) changes the people going, not the one who cancelled
        $this->actingAs($this->admin());
        $schedule->approve();
        $this->assertSame(ScheduleStatus::Cancelled, ScheduleUser::withTrashed()->where('user_id', $maria->id)->value('status'));
    }

    public function test_taken_off_by_the_owner_or_admin_is_cancelled_and_adding_them_again_works(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $maria = $this->volunteer('Maria');
        $schedule = $this->schedule($owner);
        $participant = $schedule->schedule_users()->create(['user_id' => $maria->id]);

        // what the admin form's participants list and the API participant list do when someone is removed
        $this->actingAs($owner);
        $participant->delete();
        $this->assertTrue($participant->fresh()->isCancelled());

        // added again: one row per person and schedule, and it counts again
        $schedule->schedule_users()->create(['user_id' => $maria->id]);
        $this->assertSame(1, ScheduleUser::withTrashed()->where('schedule_id', $schedule->id)->count());
        $this->assertSame(1, $schedule->schedule_users()->count());
    }

    public function test_the_calendar_does_not_count_who_cancelled(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $schedule = $this->schedule($owner);
        foreach (['Maria', 'Rui'] as $name) {
            $schedule->schedule_users()->create(['user_id' => $this->volunteer($name)->id]);
        }
        $schedule->schedule_users()->first()->delete();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin());
        $events = Livewire::test(CalendarWidget::class)->instance()->fetchEvents([
            'start' => now()->toIso8601String(), 'end' => now()->addWeek()->toIso8601String(),
        ]);

        $this->assertStringStartsWith('Olga Owner +1', $events[0]['title']);
    }

    public function test_the_admin_panel_lists_who_cancelled_and_only_editors_delete_them_for_good(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $maria = $this->volunteer('Maria');
        $schedule = $this->schedule($owner);
        $schedule->schedule_users()->create(['user_id' => $maria->id])->delete();
        $cancelled = ScheduleUser::onlyTrashed()->first();
        Filament::setCurrentPanel('admin');

        // no tab while nobody cancelled
        $other = $this->schedule($owner);
        $this->assertFalse(CancelledParticipantsRelationManager::canViewForRecord($other, ViewSchedule::class));

        // a volunteer sees who cancelled, without the delete
        $this->actingAs($this->volunteer('Rui'));
        $this->assertTrue(CancelledParticipantsRelationManager::canViewForRecord($schedule, ViewSchedule::class));
        Livewire::test(CancelledParticipantsRelationManager::class, ['ownerRecord' => $schedule, 'pageClass' => ViewSchedule::class])
            ->assertCanSeeTableRecords([$cancelled])
            ->assertSee('Maria')
            ->assertActionHidden(TestAction::make('removeForGood')->table($cancelled));

        // whoever edits other people's schedules removes it for good, and nobody is told again
        Notification::fake();
        $this->actingAs($this->admin());
        Livewire::test(CancelledParticipantsRelationManager::class, ['ownerRecord' => $schedule, 'pageClass' => EditSchedule::class])
            ->callAction(TestAction::make('removeForGood')->table($cancelled));
        $this->assertNull(ScheduleUser::withTrashed()->find($cancelled->id));
        Notification::assertNothingSent();
    }

    public function test_an_editor_restores_a_cancelled_participant_like_joining_again(): void
    {
        $owner = $this->volunteer('Olga Owner');
        $maria = $this->volunteer('Maria');
        $schedule = $this->schedule($owner);
        $schedule->schedule_users()->create(['user_id' => $maria->id])->delete();
        $cancelled = ScheduleUser::onlyTrashed()->first();
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->volunteer('Rui'));
        Livewire::test(CancelledParticipantsRelationManager::class, ['ownerRecord' => $schedule, 'pageClass' => ViewSchedule::class])
            ->assertActionHidden(TestAction::make('restoreParticipant')->table($cancelled));

        Notification::fake();
        $this->actingAs($this->admin());
        Livewire::test(CancelledParticipantsRelationManager::class, ['ownerRecord' => $schedule, 'pageClass' => ViewSchedule::class])
            ->callAction(TestAction::make('restoreParticipant')->table($cancelled));

        $row = ScheduleUser::find($cancelled->id);
        $this->assertNotNull($row);
        $this->assertSame(ScheduleStatus::Approved, $row->status);
        Notification::assertSentTo($maria, \App\Notifications\ScheduleParticipantAdded::class);
    }

    public function test_restoring_follows_the_primary_rule(): void
    {
        $novo = $this->volunteer('Nuno Novo');
        $other = $this->volunteer('Nina Nova');
        $type = ScheduleType::create(['name' => 'Abrigo', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => true]);
        $start = now()->addDays(3)->setTime(10, 0);
        $schedule = new Schedule([
            'schedule_type_id' => $type->id, 'user_id' => $novo->id, 'status' => ScheduleStatus::Approved,
            'start' => $start, 'end' => $start->copy()->addHours(2),
        ]);
        $schedule->saveQuietly();
        $cancelled = ScheduleUser::withoutEvents(function () use ($schedule, $other) {
            $row = ScheduleUser::create(['schedule_id' => $schedule->id, 'user_id' => $other->id, 'status' => ScheduleStatus::Cancelled]);
            $row->delete();

            return $row;
        });
        $this->assertTrue(ScheduleUser::withTrashed()->find($cancelled->id)->isCancelled());
        Filament::setCurrentPanel('admin');

        // two people who may not go alone, nobody who may: not approved, so not put back either
        $this->actingAs($this->admin());
        try {
            \App\Services\ScheduleParticipation::restore($cancelled);
            $this->fail('The primary rule should refuse it.');
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->assertTrue(ScheduleUser::withTrashed()->find($cancelled->id)->isCancelled());
    }
}
