<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\Users\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A user's View and Edit pages list the changes made to that user, for who may see audits. */
class UserAuditsTabTest extends TestCase
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
        $role = Role::firstOrCreate(['name' => 'role-' . md5(implode(',', $permissions)), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    private function auditsOf(User $user)
    {
        return Audit::where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->getKey())->get();
    }

    public function test_the_user_pages_list_the_changes_made_to_that_user(): void
    {
        $volunteer = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => 'Before']);
        $someoneElse = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $volunteer->update(['name' => 'After', 'building_key' => true]);
        $volunteer->assignRole(Role::firstOrCreate(['name' => 'Experiente', 'guard_name' => 'web']));

        $mine = $this->auditsOf($volunteer);
        $this->assertEqualsCanonicalizing(['created', 'updated', 'roles'], $mine->pluck('event')->unique()->all());
        $theirs = $this->auditsOf($someoneElse);
        $this->assertNotEmpty($theirs);

        $this->actingAs($this->userWith(['view_any_user', 'view_user', 'update_user', 'update_any_user', 'view_any_audit', 'view_audit']));
        foreach ([ViewUser::class, EditUser::class] as $page) {
            $this->assertTrue(AuditsRelationManager::canViewForRecord($volunteer, $page));
            Livewire::test(AuditsRelationManager::class, ['ownerRecord' => $volunteer, 'pageClass' => $page])
                ->assertCanSeeTableRecords($mine)
                ->assertCanNotSeeTableRecords($theirs)
                ->sortTable('created_at')
                ->assertCanNotSeeTableRecords($theirs);
        }
        $this->get(UserResource::getUrl('view', ['record' => $volunteer]))->assertOk()
            ->assertSeeText(__('Audits'));
        $this->get(UserResource::getUrl('edit', ['record' => $volunteer]))->assertOk()
            ->assertSeeText(__('Audits'));
    }

    public function test_the_activity_tab_lists_what_that_user_did(): void
    {
        $actor = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $other = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $target = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $gone = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => 'Deleted Person']);

        $this->actingAs($actor);
        $target->update(['name' => 'Renamed By Actor']);
        $gone->delete();
        $this->actingAs($other);
        $target->update(['building_key' => true]);

        $done = Audit::where('user_type', $actor->getMorphClass())->where('user_id', $actor->id)->get();
        $this->assertCount(2, $done);
        $notMine = Audit::whereNotIn('id', $done->pluck('id'))->get();

        $this->actingAs($this->userWith(['view_any_user', 'view_user', 'update_user', 'update_any_user', 'view_any_audit', 'view_audit']));
        foreach ([ViewUser::class, EditUser::class] as $page) {
            $this->assertTrue(ActivityRelationManager::canViewForRecord($actor, $page));
            Livewire::test(ActivityRelationManager::class, ['ownerRecord' => $actor, 'pageClass' => $page])
                ->assertCanSeeTableRecords($done)
                ->assertCanNotSeeTableRecords($notMine)
                ->assertSee('Renamed By Actor')
                // deleted (soft) users are still named
                ->assertSee('Deleted Person')
                ->filterTable('auditable_type', \App\Models\Schedule::class)
                ->assertCanNotSeeTableRecords($done);
        }
        $this->get(UserResource::getUrl('edit', ['record' => $actor]))->assertOk()
            ->assertSeeText(__('Activity'));
    }

    public function test_joining_and_leaving_a_schedule_show_the_schedule_and_who(): void
    {
        $volunteer = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => 'Joining Volunteer']);
        $type = \App\Models\ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $start = now()->addDays(2)->setTime(10, 0);
        $schedule = \App\Models\Schedule::withoutEvents(fn() => \App\Models\Schedule::create([
            // someone else's schedule: the owner is never also a participant
            'schedule_type_id' => $type->id, 'user_id' => User::factory()->create()->id,
            'status' => \App\Enums\ScheduleStatus::Pending, 'start' => $start, 'end' => $start->copy()->addHour(),
        ]));

        $this->actingAs($volunteer);
        \App\Models\ScheduleUser::create(['schedule_id' => $schedule->id, 'user_id' => $volunteer->id])->delete();
        $done = Audit::where('user_id', $volunteer->id)->where('auditable_type', \App\Models\ScheduleUser::class)->get();
        $this->assertSame(['created', 'deleted'], $done->sortBy('id')->pluck('event')->values()->all());

        $this->actingAs($this->userWith(['view_any_user', 'view_user', 'view_any_schedule', 'view_schedule', 'view_any_audit', 'view_audit']));
        Livewire::test(ActivityRelationManager::class, ['ownerRecord' => $volunteer, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords($done)
            // the row is gone after leaving: name and schedule come from the audit's values
            ->assertSee('Joining Volunteer')
            ->assertSee("#{$schedule->id}")
            ->filterTable('auditable_type', \App\Models\ScheduleUser::class)
            ->assertCanSeeTableRecords($done);
    }

    public function test_without_the_audit_permission_there_is_no_audits_list(): void
    {
        $volunteer = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($this->userWith(['view_any_user', 'view_user', 'update_user', 'update_any_user']));

        foreach ([ViewUser::class, EditUser::class] as $page) {
            $this->assertFalse(AuditsRelationManager::canViewForRecord($volunteer, $page));
            $this->assertFalse(ActivityRelationManager::canViewForRecord($volunteer, $page));
        }
        $this->get(UserResource::getUrl('edit', ['record' => $volunteer]))->assertOk()
            ->assertDontSeeText(__('Audits'))
            ->assertDontSeeText(__('Activity'));
    }
}
