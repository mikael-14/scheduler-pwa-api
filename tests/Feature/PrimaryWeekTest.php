<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The PWA home strip (GET schedules/primary-week): today and the next 6 days of the primary
 * type, each approved / pending / cancelled / empty, for every approved account, counts only.
 */
class PrimaryWeekTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules/primary-week';

    private function on(ScheduleType $type, int $daysAhead, ScheduleStatus $status): Schedule
    {
        $day = now()->addDays($daysAhead)->format('Y-m-d');

        return Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => User::factory()->create()->id, 'status' => $status,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ]));
    }

    public function test_each_day_of_the_primary_type_says_whether_it_is_filled(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 09:00'));
        $primary = ScheduleType::create(['name' => 'Passeios', 'color' => '#3366aa', 'status' => true, 'range' => true, 'primary' => true]);
        $other = ScheduleType::create(['name' => 'Limpeza', 'status' => true, 'range' => true]);

        $this->on($primary, 0, ScheduleStatus::Approved);   // today: approved
        $this->on($primary, 0, ScheduleStatus::Pending);    // (more people, still approved)
        $this->on($primary, 1, ScheduleStatus::Pending);    // tomorrow: only a request
        $this->on($primary, 2, ScheduleStatus::Cancelled);  // only cancelled
        $this->on($primary, 3, ScheduleStatus::Rejected);   // only rejected
        $this->on($other, 4, ScheduleStatus::Approved);     // another type: does not count
        $this->on($primary, 7, ScheduleStatus::Approved);   // a week ahead: outside

        // any approved account, without schedule permissions
        Sanctum::actingAs(User::factory()->create(['approved_at' => now(), 'status' => true]));

        $days = $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.type.name', 'Passeios')
            ->assertJsonPath('data.type.color', '#3366aa')
            ->assertJsonCount(7, 'data.days')
            ->json('data.days');

        $this->assertSame('2026-11-13', $days[0]['date']);
        $this->assertSame('2026-11-19', $days[6]['date']);
        $this->assertSame(['approved', 'pending', 'cancelled', 'cancelled', 'empty', 'empty', 'empty'], array_column($days, 'state'));
        $this->assertSame([1, 1], [$days[0]['approved'], $days[0]['pending']]);
        $this->assertSame(1, $days[2]['cancelled']);
        // counts only: nobody's name or id
        $this->assertSame(['date', 'approved', 'pending', 'cancelled', 'state'], array_keys($days[0]));
    }

    public function test_no_primary_type_and_who_may_ask(): void
    {
        ScheduleType::create(['name' => 'Limpeza', 'status' => true, 'range' => true]);

        $this->getJson(self::URL)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['approved_at' => null, 'status' => true]));
        $this->getJson(self::URL)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['approved_at' => now(), 'status' => true]));
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.type', null)->assertJsonCount(0, 'data.days');
    }
}
