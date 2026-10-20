<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Widgets\PendingSchedulesWidget;
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

/** The "View" modal of the pending-schedules widget: decide with an optional message. */
class ReviewPendingScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function setUpScenario(): array
    {
        Filament::setCurrentPanel('admin');

        $approver = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => 'Ana Aprovadora']);
        $role = Role::firstOrCreate(['name' => 'approver', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'approve_schedule', 'guard_name' => 'web']));
        $approver->assignRole($role);
        $this->actingAs($approver);

        $owner = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');
        $schedule = Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Pending,
            'start' => "$day 10:00", 'end' => "$day 12:00", 'internal_note' => 'first note',
        ]);

        return [$schedule, $approver];
    }

    #[RunInSeparateProcess]
    public function test_approving_from_the_modal_appends_the_message_to_the_internal_note(): void
    {
        [$schedule] = $this->setUpScenario();

        Livewire::test(PendingSchedulesWidget::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('review')->table($schedule), ['message' => 'Bring gloves'])
            ->assertHasNoActionErrors();

        $schedule->refresh();
        $this->assertSame(ScheduleStatus::Approved, $schedule->status);
        $this->assertStringStartsWith("first note\n[", $schedule->internal_note);
        $this->assertStringContainsString('Ana Aprovadora · ', $schedule->internal_note);
        $this->assertStringEndsWith(': Bring gloves', $schedule->internal_note);
    }

    #[RunInSeparateProcess]
    public function test_rejecting_from_the_modal_records_the_reason(): void
    {
        [$schedule] = $this->setUpScenario();

        Livewire::test(PendingSchedulesWidget::class)
            ->callAction(
                \Filament\Actions\Testing\TestAction::make('review')->table($schedule),
                ['message' => 'Type is full that day'],
                ['decision' => 'reject'],
            )
            ->assertHasNoActionErrors();

        $schedule->refresh();
        $this->assertSame(ScheduleStatus::Rejected, $schedule->status);
        $this->assertStringContainsString(': Type is full that day', $schedule->internal_note);
    }

    #[RunInSeparateProcess]
    public function test_empty_message_leaves_the_note_untouched_and_decided_schedules_are_not_redecided(): void
    {
        [$schedule] = $this->setUpScenario();

        Livewire::test(PendingSchedulesWidget::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('review')->table($schedule), ['message' => '  ']);

        $this->assertSame('first note', $schedule->fresh()->internal_note);
        $this->assertSame(ScheduleStatus::Approved, $schedule->fresh()->status);

        // someone else decided in the meantime: a stale modal must not overwrite it
        $schedule->refresh()->update(['status' => ScheduleStatus::Rejected]);
        // it has left the pending list, so the action can no longer even be resolved
        $this->expectException(\Filament\Actions\Exceptions\ActionNotResolvableException::class);
        try {
            Livewire::test(PendingSchedulesWidget::class)
                ->callAction(\Filament\Actions\Testing\TestAction::make('review')->table($schedule), ['message' => 'late']);
        } finally {
            $this->assertSame(ScheduleStatus::Rejected, $schedule->fresh()->status);
            $this->assertStringNotContainsString('late', (string) $schedule->fresh()->internal_note);
        }
    }
}
