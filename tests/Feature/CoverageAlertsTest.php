<?php

namespace Tests\Feature;

use App\Enums\DigestFrequency;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\CoverageAlert;
use App\Services\CoverageChecker;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The coming days with nobody approved for a schedule type: an urgent and a look-ahead summary. */
class CoverageAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-20'; // a Tuesday

    private ScheduleType $walks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::TODAY . ' 09:00', 'Europe/Lisbon'));
        $this->walks = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => true]);
        // the shipped defaults: the next 7 days, tomorrow urgent
        config([
            'coverage.enabled' => true,
            'coverage.run_at' => ['09:00', '18:00'],
            'coverage.defaults.look_ahead_days' => 7,
            'coverage.defaults.urgent_days' => 1,
            'coverage.rules' => ['walks' => ['type' => 'Passeios']],
        ]);
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

    /** The stored data of each summary sent to $user, by level. */
    private function summaries(User $user): array
    {
        $sent = [];
        Notification::assertSentTo($user, CoverageAlert::class, function (CoverageAlert $alert) use ($user, &$sent) {
            $data = $alert->toDatabase($user);
            $sent[$data['level']] = $data;

            return true;
        });
        ksort($sent);

        return $sent;
    }

    /** The days of each summary sent to $user: [level => [dates]] */
    private function daysSent(User $user): array
    {
        return array_map(
            fn(array $data) => array_map(fn(array $link) => substr($link['path'], -10), $data['links']),
            $this->summaries($user),
        );
    }

    public function test_an_empty_week_sends_one_urgent_and_one_look_ahead_summary_to_everyone_once(): void
    {
        $volunteer = $this->user(['locale' => 'pt']);
        $inactive = $this->user(['status' => false]);
        $pending = $this->user(['approved_at' => null]);
        // only joins: "book it" is not for them
        $externo = $this->user([], canBook: false);

        $this->artisan('coverage:check')->assertSuccessful();

        // two notifications, not seven
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 2);
        $this->assertSame([
            'ahead' => ['2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27'],
            'urgent' => ['2026-10-21'],
        ], $this->daysSent($volunteer));
        Notification::assertNotSentTo($inactive, CoverageAlert::class);
        Notification::assertNotSentTo($pending, CoverageAlert::class);
        Notification::assertNotSentTo($externo, CoverageAlert::class);
        $this->assertSame(7, DB::table('coverage_alerts')->count());

        // what they say, and where they lead
        ['urgent' => $urgent, 'ahead' => $ahead] = $this->summaries($volunteer);
        $this->assertSame('danger', $urgent['status']);
        $this->assertSame('Urgente: ainda ninguém para Passeios amanhã', $urgent['title']);
        $this->assertStringContainsString('• quarta-feira, 21 de outubro (amanhã)', $urgent['body']);
        $this->assertSame("/schedules/new?type={$this->walks->id}&start=2026-10-21", $urgent['path']);

        $this->assertSame('warning', $ahead['status']);
        $this->assertSame('Ainda ninguém para Passeios em 6 dias', $ahead['title']);
        $this->assertSame('quinta-feira, 22 de outubro', $ahead['links'][0]['label']);
        $this->assertSame("/schedules/new?type={$this->walks->id}&start=2026-10-22", $ahead['path']);
        $this->assertStringContainsString('Toque num dia para marcar', $ahead['summary']);

        // a second run the same day sends nothing new
        $this->artisan('coverage:check')->assertSuccessful();
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 2);
    }

    public function test_every_run_of_the_day_sends_both_summaries_once(): void
    {
        $volunteer = $this->user();
        $week = [
            'ahead' => ['2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27'],
            'urgent' => ['2026-10-21'],
        ];

        // 09:00
        $this->artisan('coverage:check')->assertSuccessful();
        $this->assertSame($week, $this->daysSent($volunteer));

        // started again later that morning: still the 09:00 run, nothing new
        $this->travelTo(Carbon::parse('2026-10-20 11:30', 'Europe/Lisbon'));
        Notification::fake();
        $this->artisan('coverage:check')->assertSuccessful();
        Notification::assertNothingSent();

        // 18:00 (as the scheduler starts it, even late): both again, the same days
        $this->travelTo(Carbon::parse('2026-10-20 18:01', 'Europe/Lisbon'));
        $this->artisan('coverage:check --at=18:00')->assertSuccessful();
        $this->assertSame($week, $this->daysSent($volunteer));
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 2);
        $this->assertSame(['09:00', '18:00'], DB::table('coverage_alerts')->distinct()->orderBy('run_at')->pluck('run_at')->all());

        // next morning: the window has moved on
        $this->travelTo(Carbon::parse('2026-10-21 09:00', 'Europe/Lisbon'));
        Notification::fake();
        $this->artisan('coverage:check --at=09:00')->assertSuccessful();
        $this->assertSame([
            'ahead' => ['2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28'],
            'urgent' => ['2026-10-22'],
        ], $this->daysSent($volunteer));
    }

    public function test_the_run_times_are_read_from_a_list_or_a_comma_separated_string(): void
    {
        config(['coverage.run_at' => '18:00, 9:00,not a time,09:00']);
        $this->assertSame(['09:00', '18:00'], CoverageChecker::runTimes());

        config(['coverage.run_at' => ['12:30']]);
        $this->assertSame(['12:30'], CoverageChecker::runTimes());

        config(['coverage.run_at' => []]);
        $this->assertSame(['09:00'], CoverageChecker::runTimes());

        config(['coverage.run_at' => ['09:00', '18:00']]);
        $this->assertSame('00:00', CoverageChecker::runAt(Carbon::parse('2026-10-20 08:59')));
        $this->assertSame('09:00', CoverageChecker::runAt(Carbon::parse('2026-10-20 17:59')));
        $this->assertSame('18:00', CoverageChecker::runAt(Carbon::parse('2026-10-20 23:00')));

        $this->artisan('coverage:check --at=25:00')->assertFailed();
    }

    public function test_an_approved_schedule_covers_the_day_and_pending_ones_do_not(): void
    {
        $volunteer = $this->user(['locale' => 'pt']);
        $this->scheduleOn('2026-10-21', ScheduleStatus::Approved); // tomorrow: covered
        $this->scheduleOn('2026-10-27', ScheduleStatus::Pending);  // next week: only pending

        $this->artisan('coverage:check')->assertSuccessful();

        // no urgent one: tomorrow is covered
        Notification::assertSentToTimes($volunteer, CoverageAlert::class, 1);
        $links = $this->summaries($volunteer)['ahead']['links'];
        $this->assertSame('terça-feira, 27 de outubro · 1 pedido a aguardar aprovação', end($links)['label']);
        $this->assertSame(1, (int) DB::table('coverage_alerts')->where('date', '2026-10-27')->value('pending_count'));
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
        $volunteer = $this->user();
        // the type can only be booked until next Monday: next Tuesday (7 days) is outside
        $this->walks->update(['end' => '2026-10-26 23:59']);
        // and only weekends are watched: tomorrow (Wednesday) is not
        config(['coverage.rules.walks.weekdays' => [6, 7]]);

        $this->artisan('coverage:check')->assertSuccessful();

        $this->assertSame(['ahead' => ['2026-10-24', '2026-10-25']], $this->daysSent($volunteer));
    }

    public function test_settings_per_rule_minimum_audience_and_days(): void
    {
        $volunteerRole = Role::create(['name' => 'volunteer', 'guard_name' => 'web']);
        $volunteer = $this->user(['locale' => 'pt']);
        $volunteer->assignRole($volunteerRole);
        $other = $this->user();
        config(['coverage.rules.walks' => ['type' => 'Passeios', 'min_approved' => 2, 'audience' => ['roles' => ['volunteer']],
            'look_ahead_days' => 3, 'urgent_days' => 3]]);
        // one approved is not enough when two are needed
        $this->scheduleOn('2026-10-21', ScheduleStatus::Approved);

        $this->artisan('coverage:check')->assertSuccessful();

        // the 3 days are all urgent: one summary
        Notification::assertNotSentTo($other, CoverageAlert::class);
        $this->assertSame(['urgent' => ['2026-10-21', '2026-10-22', '2026-10-23']], $this->daysSent($volunteer));
        $this->assertSame('Urgente: ainda ninguém para Passeios', $this->summaries($volunteer)['urgent']['title']);
    }

    public function test_urgent_summaries_are_not_kept_for_the_email_digest(): void
    {
        $user = $this->user(['email_digest' => DigestFrequency::Daily, 'email_notification' => true]);
        $days = [['date' => '2026-10-21', 'days_before' => 1, 'pending' => 0]];

        $this->assertFalse((new CoverageAlert($this->walks, CoverageAlert::URGENT, $days))->goesToDigest($user));
        $this->assertTrue((new CoverageAlert($this->walks, CoverageAlert::AHEAD, $days))->goesToDigest($user));
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
        $this->artisan('coverage:check --force --date=2026-11-02 --look-ahead=3 --urgent=0')->assertSuccessful();
        $this->assertSame(['ahead' => ['2026-11-03', '2026-11-04', '2026-11-05']], $this->daysSent($volunteer));
    }

    public function test_the_urgent_summary_is_listed_first_and_pushed_last(): void
    {
        // really stored (not faked): the order is the lists'
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        $volunteer = $this->user(['email_notification' => false, 'all_email_notification' => false]);
        config(['coverage.rules.walks' => ['type' => 'Passeios', 'look_ahead_days' => 3, 'urgent_days' => 1]]);

        $this->artisan('coverage:check')
            ->expectsTable(
                ['Rule', 'Type', 'Day', 'Days ahead', 'Summary', 'Approved', 'Pending', 'Result', 'Recipients'],
                [
                    ['walks', 'Passeios', '2026-10-21', 1, 'urgent', 0, 0, 'sent', 1],
                    ['walks', 'Passeios', '2026-10-22', 2, 'ahead', 0, 0, 'sent', 1],
                    ['walks', 'Passeios', '2026-10-23', 3, 'ahead', 0, 0, 'sent', 1],
                ],
            )
            ->assertSuccessful();

        // sent look-ahead first: on a phone the last push (the urgent one) is on top
        $this->assertSame(['ahead', 'ahead', 'urgent'], DB::table('coverage_alerts')->orderBy('id')->pluck('level')->all());

        // same second; the lists (bell, PWA, email summary) show the urgent one first
        $this->assertSame(1, $volunteer->notifications()->distinct()->count('created_at'));
        $this->assertSame(['urgent', 'ahead'], $volunteer->notifications()->get()->pluck('data.level')->all());

        // the PWA gets the short text and a link per day
        Sanctum::actingAs($volunteer);
        $list = $this->getJson('/api/notifications')->assertOk()->json('data');
        $this->assertSame(['danger', 'warning'], array_column($list, 'status'));
        $this->assertSame('coverage', $list[1]['kind']);
        $this->assertCount(2, $list[1]['links']);
        $this->assertSame("/schedules/new?type={$this->walks->id}&start=2026-10-23", $list[1]['links'][1]['path']);
        $this->assertStringNotContainsString('•', $list[1]['body']);
    }

    public function test_both_summaries_are_pushed_even_when_the_look_ahead_email_waits_for_the_digest(): void
    {
        // really stored and pushed (the test queue is sync); the push service only records the titles
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        $titles = [];
        $webPush = \Mockery::mock(\Minishlink\WebPush\WebPush::class);
        $webPush->shouldReceive('queueNotification')->andReturnUsing(function ($subscription, $payload) use (&$titles) {
            $titles[] = json_decode($payload, true)['title'];
        });
        $webPush->shouldReceive('flush')->andReturnUsing(fn() => yield from []);
        $this->app->instance(\NotificationChannels\WebPush\WebPushChannel::class, new \NotificationChannels\WebPush\WebPushChannel($webPush, app(\NotificationChannels\WebPush\ReportHandler::class)));

        config(['coverage.rules.walks' => ['type' => 'Passeios', 'look_ahead_days' => 3, 'urgent_days' => 1]]);
        $volunteer = $this->user(['locale' => 'en', 'push_notification' => true, 'email_notification' => true, 'email_digest' => DigestFrequency::Daily]);
        $volunteer->updatePushSubscription('https://push.example/phone', 'key', 'token');

        $this->artisan('coverage:check')->assertSuccessful();

        // look-ahead first, urgent last (on top on the phone)
        $this->assertSame(['Nobody yet for Passeios on 2 days', 'Urgent: nobody yet for Passeios tomorrow'], $titles);
    }

    public function test_one_email_lists_both_summaries(): void
    {
        config(['coverage.rules.walks' => ['type' => 'Passeios', 'look_ahead_days' => 3, 'urgent_days' => 1]]);
        $summary = $this->user(['locale' => 'en', 'email_notification' => true, 'email_digest' => DigestFrequency::Twice]);
        $instant = $this->user(['locale' => 'en', 'email_notification' => true, 'email_digest' => DigestFrequency::Instant]);

        $this->artisan('coverage:check')->assertSuccessful();

        foreach ([$summary, $instant] as $user) {
            Notification::assertSentTo($user, CoverageAlert::class, function (CoverageAlert $alert) use ($user) {
                $level = $alert->toDatabase($user)['level'];

                if ($level === CoverageAlert::AHEAD) {
                    // its days are in the urgent email: no email of its own, none waiting for the summary
                    $this->assertSame(['database'], $alert->via($user));
                    $this->assertFalse($alert->goesToDigest($user));
                } else {
                    $this->assertSame(['database', 'mail'], $alert->via($user));
                    app()->setLocale('en'); // as the mail channel does for the recipient
                    $lines = implode("\n", $alert->toMail($user)->introLines);
                    $this->assertStringContainsString('Wednesday, 21 October (tomorrow)', $lines);
                    $this->assertStringContainsString("Also nobody yet in the coming days:\n• Thursday, 22 October\n• Friday, 23 October", $lines);
                }

                return true;
            });
        }

        // a look-ahead with no urgent one keeps its own email (here: waiting for the summary)
        config(['coverage.rules.walks' => ['type' => 'Passeios', 'look_ahead_days' => 3, 'urgent_days' => 0]]);
        Notification::fake();
        $this->artisan('coverage:check --date=2026-10-21')->assertSuccessful();
        Notification::assertSentTo($summary, CoverageAlert::class, fn(CoverageAlert $alert) => $alert->goesToDigest($summary));
        Notification::assertSentTo($instant, CoverageAlert::class, fn(CoverageAlert $alert) => $alert->via($instant) === ['database', 'mail']);
    }

    public function test_the_urgent_push_leaves_30_seconds_after_the_look_ahead_one(): void
    {
        $volunteer = $this->user(['push_notification' => true]);
        $volunteer->updatePushSubscription('https://push.example/phone', 'key', 'token');

        // stored as coverage:check stores them: look-ahead first, then urgent
        foreach ([CoverageAlert::AHEAD, CoverageAlert::URGENT] as $level) {
            $volunteer->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => CoverageAlert::class,
                'data' => ['title' => 'T', 'body' => 'B', 'level' => $level],
            ]);
        }

        $delays = [];
        Notification::assertSentTo($volunteer, \App\Notifications\PwaWebPushNotification::class,
            function ($push) use (&$delays) {
                $level = \Illuminate\Notifications\DatabaseNotification::find($push->storedId())->data['level'];
                $delays[$level] = $push->delay ? (int) round(now()->diffInSeconds($push->delay)) : 0;

                return true;
            }
        );
        $this->assertSame([CoverageAlert::AHEAD => 0, CoverageAlert::URGENT => 30], $delays);
    }

    public function test_the_check_is_scheduled_at_each_configured_time(): void
    {
        // the shipped default: 09:00 and 18:00
        $events = collect(app(Scheduler::class)->events())->filter(fn($event) => str_contains($event->command, 'coverage:check'))->values();

        $this->assertCount(2, $events, 'coverage:check is not scheduled twice a day');
        $this->assertSame(['0 9 * * *', '0 18 * * *'], $events->pluck('expression')->all());
        $this->assertStringContainsString('--at=09:00', $events[0]->command);
        $this->assertStringContainsString('--at=18:00', $events[1]->command);
        $this->assertSame('Europe/Lisbon', $events[0]->timezone);

        // the email summaries at the same times, run after the check (a look-ahead waiting for one goes out at once)
        $all = collect(app(Scheduler::class)->events())->values();
        $digests = $all->filter(fn($event) => str_contains($event->command, 'notifications:digest'));
        $this->assertSame(['0 9 * * *', '0 18 * * *'], $digests->pluck('expression')->values()->all());
        $this->assertGreaterThan($all->search($events[0]), $digests->keys()->first());
    }
}
