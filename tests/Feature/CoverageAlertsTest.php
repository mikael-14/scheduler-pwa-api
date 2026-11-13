<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\CoverageAlert;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Days with nobody approved for a schedule type warn everyone a week and a day before. */
class CoverageAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-20'; // a Tuesday

    private ScheduleType $walks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse(self::TODAY . ' 09:00', 'Europe/Lisbon'));
        $this->walks = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => true]);
        config(['coverage.enabled' => true, 'coverage.rules' => ['walks' => ['type' => 'Passeios']]]);
        Notification::fake();
    }

    /** Someone who can book (the alert asks them to); $canBook false: e.g. an Externo, who only joins. */
    private function user(array $attributes = [], bool $canBook = true): User
    {
        $user = User::factory()->create(array_merge(['approved_at' => now(), 'status' => true], $attributes));
        $role = $canBook ? 'booker' : 'joiner';
        $permission = $canBook ? 'create_schedule' : 'participate_schedule';
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
            ->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        $user->assignRole($role);

        return $user;
    }

    private function scheduleOn(string $day, ScheduleStatus $status, array $attributes = []): Schedule
    {
        return Schedule::withoutEvents(fn() => Schedule::create(array_merge([
            'schedule_type_id' => $this->walks->id, 'user_id' => $this->user()->id, 'status' => $status,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ], $attributes)));
    }

    public function test_empty_days_a_week_and_a_day_ahead_warn_everyone_once(): void
    {
        $volunteer = $this->user(['locale' => 'pt']);
        $inactive = $this->user(['status' => false]);
        $pending = $this->user(['approved_at' => null]);
        // only joins: "book it" is not for them
        $externo = $this->user([], canBook: false);

        $this->artisan('coverage:check')->assertSuccessful();

        // 2026-10-27 (7 days) and 2026-10-21 (1 day), both empty
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 2);
        Notification::assertNotSentTo($inactive, CoverageAlert::class);
        Notification::assertNotSentTo($pending, CoverageAlert::class);
        Notification::assertNotSentTo($externo, CoverageAlert::class);
        $this->assertSame(['2026-10-21', '2026-10-27'], DB::table('coverage_alerts')->orderBy('date')->pluck('date')->all());

        // what it says, and where it leads
        Notification::assertSentTo($volunteer, CoverageAlert::class, function (CoverageAlert $alert) use ($volunteer) {
            $data = $alert->toDatabase($volunteer);
            return $data['status'] === 'warning'
                && str_starts_with($data['title'], 'Ainda ninguém para Passeios')
                && str_contains($data['path'], "/schedules/new?type={$this->walks->id}&start=2026-10-");
        });

        // a second run the same day sends nothing new
        $this->artisan('coverage:check')->assertSuccessful();
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 2);
    }

    public function test_an_approved_schedule_covers_the_day_and_pending_ones_do_not(): void
    {
        $volunteer = $this->user();
        $this->scheduleOn('2026-10-21', ScheduleStatus::Approved); // tomorrow: covered
        $this->scheduleOn('2026-10-27', ScheduleStatus::Pending);  // next week: only pending

        $this->artisan('coverage:check')->assertSuccessful();

        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 1);
        $sent = DB::table('coverage_alerts')->sole();
        $this->assertSame('2026-10-27', $sent->date);
        $this->assertSame(1, (int) $sent->pending_count);
    }

    public function test_multi_day_and_all_day_schedules_cover_every_day_they_touch(): void
    {
        $this->user();
        $this->scheduleOn('2026-10-20', ScheduleStatus::Approved, ['start' => '2026-10-20 00:00', 'end' => '2026-10-28 00:00', 'all_day' => true]);

        $this->artisan('coverage:check')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_days_outside_the_types_dates_or_weekdays_are_not_checked(): void
    {
        $this->user();
        // the type can only be booked until next Monday: next Tuesday (7 days) is outside
        $this->walks->update(['end' => '2026-10-26 23:59']);
        // and only weekends are watched: tomorrow (Wednesday) is not
        config(['coverage.rules.walks.weekdays' => [6, 7]]);

        $this->artisan('coverage:check')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_settings_per_rule_minimum_and_audience(): void
    {
        $volunteerRole = Role::create(['name' => 'volunteer', 'guard_name' => 'web']);
        $volunteer = $this->user();
        $volunteer->assignRole($volunteerRole);
        $other = $this->user();
        config(['coverage.rules.walks' => ['type' => 'Passeios', 'min_approved' => 2, 'audience' => ['roles' => ['volunteer']]]]);
        // one approved is not enough when two are needed
        $this->scheduleOn('2026-10-21', ScheduleStatus::Approved);

        $this->artisan('coverage:check --days=1')->assertSuccessful();

        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 1);
        Notification::assertNotSentTo($other, CoverageAlert::class);
    }

    public function test_dry_run_switch_and_options(): void
    {
        $volunteer = $this->user();

        // dry run: shows, sends and records nothing
        $this->artisan('coverage:check --dry-run')->expectsOutputToContain('would send')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('coverage_alerts')->count());

        // switched off: nothing, unless forced
        config(['coverage.enabled' => false]);
        $this->artisan('coverage:check')->expectsOutputToContain('off')->assertSuccessful();
        Notification::assertNothingSent();
        $this->artisan('coverage:check --force --date=2026-11-02 --days=3')->assertSuccessful();
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 1);
        $this->assertSame('2026-11-05', DB::table('coverage_alerts')->value('date'));
    }

    public function test_the_alerts_of_one_run_are_listed_by_day_soonest_first(): void
    {
        // really stored (not faked): the order is the lists'
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        $volunteer = $this->user(['email_notification' => false, 'all_email_notification' => false]);
        config(['coverage.rules.walks' => ['type' => 'Passeios', 'notify_days_before' => [7, 1, 2]]]);

        $this->artisan('coverage:check')
            ->expectsTable(
                ['Rule', 'Type', 'Day', 'Days before', 'Approved', 'Pending', 'Result', 'Recipients'],
                [
                    ['walks', 'Passeios', '2026-10-21', 1, 0, 0, 'sent', 1],
                    ['walks', 'Passeios', '2026-10-22', 2, 0, 0, 'sent', 1],
                    ['walks', 'Passeios', '2026-10-27', 7, 0, 0, 'sent', 1],
                ],
            )
            ->assertSuccessful();

        // sent the farthest first: on a phone the last push (tomorrow) is on top
        $this->assertSame(['2026-10-27', '2026-10-22', '2026-10-21'], DB::table('coverage_alerts')->orderBy('id')->pluck('date')->all());

        // same second; the lists (bell, PWA, email summary) show tomorrow, then 2 days, then 7
        $soonestFirst = ['2026-10-21', '2026-10-22', '2026-10-27'];
        $this->assertSame(1, $volunteer->notifications()->distinct()->count('created_at'));
        $this->assertSame($soonestFirst, $volunteer->notifications()->get()->pluck('data.sort_day')->all());
        $this->assertSame($soonestFirst, $volunteer->unreadNotifications()->get()->pluck('data.sort_day')->all());
        $this->assertSame($soonestFirst, $volunteer->notifications()->reorder('created_at')->orderBy('data->sort_day')->get()->pluck('data.sort_day')->all());
    }

    public function test_the_check_is_scheduled_daily_at_the_configured_time(): void
    {
        $event = collect(app(Scheduler::class)->events())->first(fn($event) => str_contains($event->command, 'coverage:check'));

        $this->assertNotNull($event, 'coverage:check is not scheduled');
        $this->assertSame('0 9 * * *', $event->expression);
        $this->assertSame('Europe/Lisbon', $event->timezone);
    }
}
