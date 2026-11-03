<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Widgets\DashboardCounters;
use App\Filament\Widgets\PendingSchedulesWidget;
use App\Filament\Widgets\ScheduleHeadcountChart;
use App\Filament\Widgets\TodaySchedulesWidget;
use App\Filament\Widgets\WeekOverviewWidget;
use App\Livewire\CalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Services\AgendaSummary;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The admin dashboard: what waits for someone first, the week, the chart and today. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions, string $role): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    private function scheduleOf(User $owner, ScheduleType $type, CarbonImmutable $day, ScheduleStatus $status, string $from = '10:00'): Schedule
    {
        return Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => $status,
            'start' => $day->format('Y-m-d') . " $from", 'end' => $day->format('Y-m-d') . ' 12:00',
        ]));
    }

    #[RunInSeparateProcess]
    public function test_the_dashboard_shows_each_block_to_who_may_act_on_it(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = $this->userWith(['view_any_schedule', 'approve_schedule', 'approve_user', 'view_any_user'], 'admin');
        $volunteer = $this->userWith(['view_any_schedule'], 'volunteer');
        User::factory()->create(['approved_at' => null, 'status' => true, 'name' => 'Waiting Person']);
        $shelter = ScheduleType::create(['name' => 'Abrigo', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => true]);
        $this->scheduleOf($volunteer, $shelter, CarbonImmutable::today(), ScheduleStatus::Pending);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSeeLivewire(DashboardCounters::class)
            ->assertSeeLivewire(PendingSchedulesWidget::class)
            ->assertSeeLivewire(WeekOverviewWidget::class)
            ->assertSeeLivewire(ScheduleHeadcountChart::class)
            ->assertSeeLivewire(TodaySchedulesWidget::class);

        Livewire::test(DashboardCounters::class)
            ->assertSee(__('Pending Users'))
            ->assertSee(__('Pending Schedules'));

        // the pending users link opens the list filtered on them
        $approvedOne = User::factory()->create(['approved_at' => now(), 'name' => 'Already In']);
        Livewire::withQueryParams(['filters' => ['approved' => ['value' => '0']]])
            ->test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->assertCanSeeTableRecords(User::where('name', 'Waiting Person')->get())
            ->assertCanNotSeeTableRecords([$approvedOne]);

        // no approvals for someone who cannot decide
        $this->actingAs($volunteer)->get('/admin')->assertOk()
            ->assertDontSeeLivewire(PendingSchedulesWidget::class)
            ->assertSeeLivewire(WeekOverviewWidget::class);

        Livewire::test(DashboardCounters::class)
            ->assertDontSee(__('Pending Users'))
            ->assertDontSee(__('Pending Schedules'))
            ->assertSee(__('This week'));
    }

    public function test_people_are_counted_once_a_day_approved_before_pending(): void
    {
        $type = ScheduleType::create(['name' => 'Abrigo', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => true]);
        $other = ScheduleType::create(['name' => 'Passeios', 'color' => '#445566', 'status' => true, 'range' => true, 'all_day' => false]);
        $ana = User::factory()->create();
        $rui = User::factory()->create();
        $eva = User::factory()->create();
        $day = CarbonImmutable::today()->addDay();

        $approved = $this->scheduleOf($ana, $type, $day, ScheduleStatus::Approved);
        $approved->schedule_users()->createQuietly(['user_id' => $rui->id, 'status' => ScheduleStatus::Approved]);
        // Ana again, pending, later: still one approved person
        $this->scheduleOf($ana, $other, $day, ScheduleStatus::Pending, '11:00');
        $this->scheduleOf($eva, $other, $day, ScheduleStatus::Pending);
        // not counted
        $this->scheduleOf($eva, $type, $day, ScheduleStatus::Cancelled);

        $all = AgendaSummary::days($day, 1)[0];
        $this->assertSame(['schedules' => 3, 'approved' => 2, 'pending' => 1], array_intersect_key($all, array_flip(['schedules', 'approved', 'pending'])));

        $shelter = AgendaSummary::days($day, 1, $type->id)[0];
        $this->assertSame(2, $shelter['approved']);
        $this->assertSame(0, $shelter['pending']);
    }

    #[RunInSeparateProcess]
    public function test_the_chart_opens_on_the_primary_type_and_offers_the_others(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->userWith(['view_any_schedule'], 'volunteer'));
        ScheduleType::create(['name' => 'Passeios', 'color' => '#445566', 'status' => true, 'range' => true, 'all_day' => false]);
        $shelter = ScheduleType::create(['name' => 'Abrigo', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false, 'primary' => true]);

        Livewire::test(ScheduleHeadcountChart::class)
            ->assertSet('filter', (string) $shelter->id)
            ->assertSee('Passeios')
            ->assertSee(__('All types'))
            ->set('filter', 'all')
            ->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_a_day_link_opens_the_calendar_on_that_day(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->userWith(['view_any_schedule'], 'volunteer'));

        $this->get('/admin/schedule-calendar?date=2026-11-05')->assertOk();

        $config = Livewire::test(CalendarWidget::class, ['initialDate' => '2026-11-05'])->instance()->config();
        $this->assertSame('2026-11-05', $config['initialDate']);
        $this->assertSame('timeGridDay', $config['initialView']);

        // anything else is ignored
        $this->get('/admin/schedule-calendar?date=nonsense')->assertOk();
    }
}
