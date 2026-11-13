<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * How many schedules and people there are on each day, for the admin dashboard (counters, the
 * week overview, the people-per-day chart).
 *
 * Counted: pending, approved and completed schedules (not rejected, cancelled or not shown), with
 * their owner and their participants (participants not shown are not counted). A person on an
 * approved schedule counts as approved; on pending ones only, as pending. Each person once a day.
 */
class AgendaSummary
{
    private const COUNTED = [ScheduleStatus::Pending, ScheduleStatus::Approved, ScheduleStatus::Completed];

    /**
     * One row per day from $first, $days days.
     *
     * @return list<array{date: CarbonImmutable, schedules: int, approved: int, pending: int}>
     */
    public static function days(CarbonImmutable $first, int $days, ?int $typeId = null): array
    {
        $first = $first->startOfDay();
        $last = $first->addDays($days - 1)->endOfDay();
        $schedules = static::schedulesBetween($first, $last, $typeId);

        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $dayStart = $first->addDays($i);
            $dayEnd = $dayStart->endOfDay();

            $onDay = $schedules->filter(fn(Schedule $schedule) => $schedule->start <= $dayEnd
                && ($schedule->end ?? $schedule->start) >= $dayStart);

            $approved = [];
            $pending = [];
            foreach ($onDay as $schedule) {
                $people = static::people($schedule);

                if ($schedule->status === ScheduleStatus::Pending) {
                    $pending = array_merge($pending, $people);
                } else {
                    $approved = array_merge($approved, $people);
                }
            }

            $approved = array_unique($approved);
            $rows[] = [
                'date' => $dayStart,
                'schedules' => $onDay->count(),
                'approved' => count($approved),
                'pending' => count(array_diff(array_unique($pending), $approved)),
            ];
        }

        return $rows;
    }

    /** Monday to Sunday of the week of $day. */
    public static function week(?CarbonImmutable $day = null, ?int $typeId = null): array
    {
        return static::days(($day ?? CarbonImmutable::today())->startOfWeek(), 7, $typeId);
    }

    /** Schedules counted this week, by status: ['total' => n, 'approved' => n, 'pending' => n]. */
    public static function weekTotals(?CarbonImmutable $day = null): array
    {
        $monday = ($day ?? CarbonImmutable::today())->startOfWeek();
        $schedules = static::schedulesBetween($monday, $monday->addDays(6)->endOfDay());

        return [
            'total' => $schedules->count(),
            'approved' => $schedules->where('status', '!=', ScheduleStatus::Pending)->count(),
            'pending' => $schedules->where('status', ScheduleStatus::Pending)->count(),
        ];
    }

    /**
     * Whether each day from $first is filled for a type (the PWA's home strip): its people, how
     * many of its schedules were cancelled or rejected, and a state:
     * 'approved' (someone approved), 'pending' (only requests), 'cancelled' (only cancelled or
     * rejected schedules) or 'empty' (nothing at all).
     *
     * @return list<array{date: string, approved: int, pending: int, cancelled: int, state: string}>
     */
    public static function coverage(CarbonImmutable $first, int $days, int $typeId): array
    {
        $first = $first->startOfDay();
        $closed = Schedule::query()
            ->where('schedule_type_id', $typeId)
            ->whereIn('status', [ScheduleStatus::Cancelled, ScheduleStatus::Rejected])
            ->where('start', '<=', $first->addDays($days - 1)->endOfDay())
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$first])
            ->get(['id', 'start', 'end']);

        return array_map(function (array $day) use ($closed) {
            $dayStart = $day['date'];
            $dayEnd = $dayStart->endOfDay();
            $cancelled = $closed->filter(fn(Schedule $schedule) => $schedule->start <= $dayEnd
                && ($schedule->end ?? $schedule->start) >= $dayStart)->count();

            return [
                'date' => $dayStart->toDateString(),
                'approved' => $day['approved'],
                'pending' => $day['pending'],
                'cancelled' => $cancelled,
                'state' => match (true) {
                    $day['approved'] > 0 => 'approved',
                    $day['pending'] > 0 => 'pending',
                    $cancelled > 0 => 'cancelled',
                    default => 'empty',
                },
            ];
        }, static::days($first, $days, $typeId));
    }

    public static function primaryType(): ?ScheduleType
    {
        return ScheduleType::query()->where('primary', true)->orderBy('id')->first();
    }

    /** @return Collection<int, Schedule> */
    private static function schedulesBetween(CarbonImmutable $from, CarbonImmutable $to, ?int $typeId = null): Collection
    {
        return Schedule::query()
            ->with('schedule_users:id,schedule_id,user_id,status')
            ->whereIn('status', self::COUNTED)
            ->when($typeId, fn($query) => $query->where('schedule_type_id', $typeId))
            ->where('start', '<=', $to)
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$from])
            ->get();
    }

    /** @return list<int> the owner and the participants who count */
    private static function people(Schedule $schedule): array
    {
        return collect([$schedule->user_id])
            ->merge($schedule->schedule_users
                ->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled, ScheduleStatus::NotShown])
                ->pluck('user_id'))
            ->filter()
            ->values()
            ->all();
    }
}
