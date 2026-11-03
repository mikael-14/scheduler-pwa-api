<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Pages\CreateSchedule;
use App\Filament\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Livewire\CalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The admin panel's schedule screens follow the same rules as the API (ScheduleAccess). */
class ScheduleAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const MEMBER = ['view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule'];

    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'r' . uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function type(): ScheduleType
    {
        return ScheduleType::firstOrCreate(['name' => 'Walks'], ['color' => '#123456', 'status' => true, 'range' => true, 'all_day' => false]);
    }

    private function schedule(User $owner, string $day = '+2 days'): Schedule
    {
        $start = now()->modify($day)->setTime(10, 0);

        return Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $this->type()->id, 'user_id' => $owner->id,
            'status' => ScheduleStatus::Pending, 'start' => $start, 'end' => $start->copy()->addHour(),
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_a_member_books_only_for_themselves(): void
    {
        $member = $this->userWith(self::MEMBER);
        $other = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($member);
        $start = now()->addDays(2)->setTime(10, 0);

        Livewire::test(CreateSchedule::class)
            ->fillForm([
                'schedule_type_id' => $this->type()->id, 'user_id' => $other->id,
                'start' => $start->format(config('app.date_time_format')),
                'end' => $start->copy()->addHour()->format(config('app.date_time_format')),
            ])
            ->call('create');

        $this->assertFalse(Schedule::where('user_id', $other->id)->exists(), 'booked for someone else without create_any_schedule');

        // the owner field is theirs and locked; saving books it for them
        Livewire::test(CreateSchedule::class)
            ->assertFormFieldDisabled('user_id')
            ->assertFormSet(['user_id' => $member->id])
            ->fillForm([
                'schedule_type_id' => $this->type()->id,
                'start' => $start->format(config('app.date_time_format')),
                'end' => $start->copy()->addHour()->format(config('app.date_time_format')),
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertTrue(Schedule::where('user_id', $member->id)->exists());
    }

    public function test_with_create_any_schedule_one_books_for_others(): void
    {
        $admin = $this->userWith([...self::MEMBER, 'create_any_schedule']);
        $other = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->actingAs($admin);
        $start = now()->addDays(2)->setTime(10, 0);

        Livewire::test(CreateSchedule::class)
            ->assertFormFieldEnabled('user_id')
            ->fillForm([
                'schedule_type_id' => $this->type()->id, 'user_id' => $other->id,
                'start' => $start->format(config('app.date_time_format')),
                'end' => $start->copy()->addHour()->format(config('app.date_time_format')),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Schedule::where('user_id', $other->id)->exists());
    }

    public function test_the_owner_rule_holds_outside_the_forms_too(): void
    {
        $member = $this->userWith(self::MEMBER);
        $other = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $schedule = $this->schedule($member);
        $this->actingAs($member);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $schedule->update(['user_id' => $other->id]);
    }

    public function test_a_member_does_not_hand_their_schedule_to_someone_else(): void
    {
        $member = $this->userWith(self::MEMBER);
        $other = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $schedule = $this->schedule($member);
        $this->actingAs($member);

        Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
            ->fillForm(['user_id' => $other->id])
            ->call('save');

        $this->assertSame($member->id, $schedule->fresh()->user_id, 'handed over without update_any_schedule');
    }

    public function test_the_calendar_does_not_edit_other_peoples_schedules(): void
    {
        $member = $this->userWith(self::MEMBER);
        $owner = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $schedule = $this->schedule($owner);
        $this->actingAs($member);

        // a click opens its page instead
        Livewire::test(CalendarWidget::class)
            ->call('onEventClick', ['id' => $schedule->id])
            ->assertSet('mountedActions', [])
            ->assertRedirect(\App\Filament\Resources\Schedules\ScheduleResource::getUrl('infolist', ['record' => $schedule]));

        // and a crafted request (the popup straight away, or a drag) is refused
        foreach ([
            fn($c) => $c->call('mountAction', 'edit', ['event' => $schedule->id]),
            fn($c) => $c->call('onEventDrop', ['id' => $schedule->id, 'start' => now()->addDays(3)->toIso8601String()], [], [], [], null, null),
        ] as $attempt) {
            $component = $attempt(Livewire::test(CalendarWidget::class));
            if ($component->get('mountedActions')) {
                $component->set('mountedActions.0.data.description', 'changed by someone else')->callMountedAction();
            }
            $this->assertNull($schedule->fresh()->description);
        }

        // nor are schedules they cannot see (someone else's cancelled one)
        $hidden = $this->schedule($owner);
        Schedule::withoutEvents(fn() => $hidden->update(['status' => ScheduleStatus::Cancelled]));
        Livewire::test(CalendarWidget::class)
            ->call('onEventClick', ['id' => $hidden->id])
            ->assertSet('mountedActions', [])
            ->assertNoRedirect();

        // their own one opens and saves, still theirs even if another owner is sent
        $own = $this->schedule($member);
        Livewire::test(CalendarWidget::class)
            ->call('onEventClick', ['id' => $own->id])
            ->set('mountedActions.0.data.description', 'mine')
            ->set('mountedActions.0.data.user_id', (string) $owner->id)
            ->callMountedAction();
        $this->assertSame('mine', $own->fresh()->description);
        $this->assertSame($member->id, $own->fresh()->user_id);
    }

    public function test_bulk_delete_skips_what_cannot_be_deleted_one_by_one(): void
    {
        $admin = $this->userWith([...self::MEMBER, 'delete_any_schedule']);
        $past = $this->schedule(User::factory()->create(), '-3 days');
        $future = $this->schedule(User::factory()->create());
        $this->actingAs($admin);

        Livewire::test(ListSchedules::class)
            ->set('activeTab', null)
            ->selectTableRecords([$past->id, $future->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertSoftDeleted($future);
        $this->assertNotSoftDeleted($past);
    }
}
