<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Resources\Schedules\Pages\ViewSchedule;
use App\Livewire\CalendarWidget;
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

/** The admin panel follows the API: the internal note is read and written by staff only. */
class InternalNotePanelTest extends TestCase
{
    use RefreshDatabase;

    private const NOTE = 'Staff only remark';

    private function userWith(string $role, array $permissions): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    /** A plain user's own future schedule, with a note written by staff. */
    private function scenario(): array
    {
        Filament::setCurrentPanel('admin');
        $basic = ['view_any_schedule', 'view_schedule', 'update_schedule'];

        $owner = $this->userWith('member', $basic);
        $staff = $this->userWith('manager', [...$basic, 'update_any_schedule']);

        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');
        $schedule = Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Pending,
            'start' => "$day 10:00", 'end' => "$day 12:00", 'internal_note' => self::NOTE,
        ]));

        return [$schedule, $owner, $staff];
    }

    #[RunInSeparateProcess]
    public function test_plain_users_never_receive_the_note(): void
    {
        [$schedule, $owner] = $this->scenario();
        $this->actingAs($owner);

        // not on the page, and not in the component state sent to the browser either
        Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
            ->assertFormFieldIsHidden('internal_note')
            ->assertSet('data.internal_note', null)
            ->assertDontSee(self::NOTE);

        Livewire::test(ViewSchedule::class, ['record' => $schedule->getRouteKey()])
            ->assertSet('data.internal_note', null)
            ->assertDontSee(self::NOTE);

        Livewire::test(ListSchedules::class)
            ->assertTableColumnHidden('internal_note')
            ->assertDontSee(self::NOTE);

        Livewire::test(CalendarWidget::class)
            ->call('onEventClick', ['id' => $schedule->id])
            ->assertSet('mountedActions.0.data.internal_note', null)
            ->assertDontSee(self::NOTE);
    }

    #[RunInSeparateProcess]
    public function test_plain_users_cannot_change_the_note_by_sending_it(): void
    {
        [$schedule, $owner] = $this->scenario();
        $this->actingAs($owner);

        Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
            ->set('data.internal_note', 'overwritten')
            ->set('data.description', 'Bring the keys')
            ->call('save')
            ->assertHasNoFormErrors();

        $schedule->refresh();
        $this->assertSame('Bring the keys', $schedule->description);
        $this->assertSame(self::NOTE, $schedule->internal_note);
    }

    #[RunInSeparateProcess]
    public function test_staff_read_and_edit_the_note(): void
    {
        [$schedule, , $staff] = $this->scenario();
        $this->actingAs($staff);

        Livewire::test(ViewSchedule::class, ['record' => $schedule->getRouteKey()])
            ->assertSee(self::NOTE);

        Livewire::test(ListSchedules::class)
            ->assertTableColumnVisible('internal_note');

        Livewire::test(CalendarWidget::class)
            ->call('onEventClick', ['id' => $schedule->id])
            ->assertSet('mountedActions.0.data.internal_note', self::NOTE);

        Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
            ->assertFormFieldIsVisible('internal_note')
            ->assertFormSet(['internal_note' => self::NOTE])
            ->fillForm(['internal_note' => 'Updated by staff'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated by staff', $schedule->fresh()->internal_note);
    }
}
