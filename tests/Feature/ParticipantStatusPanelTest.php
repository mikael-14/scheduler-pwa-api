<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The admin panel's schedule form: participants share the schedule's status there too. */
class ParticipantStatusPanelTest extends TestCase
{
    use RefreshDatabase;

    #[RunInSeparateProcess]
    public function test_approving_in_the_form_approves_the_participants_and_their_status_is_not_chosen_alone(): void
    {
        Filament::setCurrentPanel('admin');

        $permissions = ['view_any_schedule', 'view_schedule', 'update_schedule', 'update_any_schedule', 'approve_schedule', 'independent_schedule'];
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $admin = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $admin->assignRole($role);
        $owner = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $participant = User::factory()->create(['approved_at' => now(), 'status' => true]);

        $type = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');
        $schedule = Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Pending,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ]));
        $schedule->schedule_users()->createQuietly(['user_id' => $participant->id, 'status' => ScheduleStatus::Pending]);

        $this->actingAs($admin);

        // approving another status for the participant alone is not possible
        $this->assertFalse(\App\Services\ScheduleAccess::mayChangeParticipantStatus(
            $admin, $schedule->schedule_users()->first(), ScheduleStatus::Pending, ScheduleStatus::Approved,
        ));

        Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
            ->set('data.status', ScheduleStatus::Approved->value)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ScheduleStatus::Approved, $schedule->fresh()->status);
        // saving the participants list afterwards does not undo it
        $this->assertSame(ScheduleStatus::Approved, $schedule->schedule_users()->value('status'));
    }
}
