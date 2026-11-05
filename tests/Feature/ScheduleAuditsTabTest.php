<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ViewSchedule;
use App\Filament\Resources\Schedules\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A schedule's View page lists its own audits (and its participants'), for who may see audits. */
class ScheduleAuditsTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Laravel Auditing skips console runs (tests count as console)
        config(['audit.console' => true]);
        // Auditable decides when a model boots whether to watch it: boot again with auditing on
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        Filament::setCurrentPanel('admin');
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::create(['name' => 'role-' . $user->id, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    private function schedule(User $owner): Schedule
    {
        $type = ScheduleType::firstOrCreate(['name' => 'Cleaning'], ['color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $start = now()->addDays(2)->setTime(10, 0);

        return Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id,
            'status' => ScheduleStatus::Pending, 'start' => $start, 'end' => $start->copy()->addHour(),
        ]);
    }

    public function test_the_view_page_lists_the_schedules_audits_including_who_left(): void
    {
        $owner = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $leaver = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => 'Leaving Volunteer']);
        [$schedule, $other] = $this->asSystem(function () use ($owner, $leaver) {
            $schedule = $this->schedule($owner);
            $other = $this->schedule($owner);
            ScheduleUser::create(['schedule_id' => $schedule->id, 'user_id' => $leaver->id])->delete();
            ScheduleUser::create(['schedule_id' => $other->id, 'user_id' => $leaver->id]);

            return [$schedule, $other];
        });

        $mine = Audit::where(fn($q) => $q->where('auditable_type', Schedule::class)->where('auditable_id', $schedule->id))
            ->orWhere(fn($q) => $q->where('auditable_type', ScheduleUser::class)->where(fn($q) => $q
                ->where('new_values->schedule_id', $schedule->id)->orWhere('old_values->schedule_id', $schedule->id)))
            ->get();
        // the schedule created, the participant added and removed
        $this->assertCount(3, $mine);
        $theirs = Audit::whereNotIn('id', $mine->pluck('id'))->get();
        $this->assertNotEmpty($theirs);

        $auditor = $this->userWith(['view_any_schedule', 'view_schedule', 'view_any_audit', 'view_audit']);
        $this->actingAs($auditor);
        $this->assertTrue(AuditsRelationManager::canViewForRecord($schedule, ViewSchedule::class));
        $this->assertTrue(AuditsRelationManager::canViewForRecord($schedule, EditSchedule::class));
        $this->get(ScheduleResource::getUrl('infolist', ['record' => $schedule]))->assertOk();

        foreach ([ViewSchedule::class, EditSchedule::class] as $page) {
            Livewire::test(AuditsRelationManager::class, ['ownerRecord' => $schedule, 'pageClass' => $page])
                ->assertCanSeeTableRecords($mine)
                ->assertCanNotSeeTableRecords($theirs)
                ->assertSee('Leaving Volunteer')
                // searching/sorting must not widen the list to other schedules
                ->sortTable('created_at')
                ->assertCanNotSeeTableRecords($theirs);
        }
    }

    public function test_the_edit_page_shows_the_audits_to_who_may_see_them(): void
    {
        $owner = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $schedule = $this->asSystem(fn() => $this->schedule($owner));
        $editors = ['view_any_schedule', 'view_schedule', 'update_schedule', 'update_any_schedule'];

        $this->actingAs($this->userWith([...$editors, 'view_any_audit', 'view_audit']));
        $this->get(ScheduleResource::getUrl('edit', ['record' => $schedule]))->assertOk()
            ->assertSeeLivewire(AuditsRelationManager::class);

        $this->actingAs($this->userWith($editors));
        $this->get(ScheduleResource::getUrl('edit', ['record' => $schedule]))->assertOk()
            ->assertDontSeeLivewire(AuditsRelationManager::class);
    }

    public function test_without_the_audit_permission_there_is_no_audits_tab(): void
    {
        $owner = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $schedule = $this->asSystem(fn() => $this->schedule($owner));
        $this->actingAs($this->userWith(['view_any_schedule', 'view_schedule']));

        $this->assertFalse(AuditsRelationManager::canViewForRecord($schedule, ViewSchedule::class));
        $this->assertFalse(AuditsRelationManager::canViewForRecord($schedule, EditSchedule::class));
        $this->get(ScheduleResource::getUrl('infolist', ['record' => $schedule]))->assertOk()
            ->assertDontSee(__('Action By'));
    }
}
