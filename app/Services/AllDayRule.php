<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;

/**
 * Whole-day schedules (no hours: "I can go Sunday, but I can't give hours").
 *
 * - Only people with `all_day_schedule` may have one, and only on types that allow it
 *   (schedule_types.all_day). Both the person booking and the owner need the permission, so a
 *   "Voluntário Novo" never gets one, not even booked for them. Checked by ScheduleRulesValidator.
 * - Since nobody knows the hours, an approved whole-day schedule takes the day for its type:
 *   nothing else of that type can be approved on its days, and it cannot be approved while
 *   something else of that type is approved there. Checked with the primary rule when approving
 *   (PrimaryScheduleRule::scheduleProblem), on every type.
 */
class AllDayRule
{
    public const PERMISSION = 'all_day_schedule';

    public static function mayBook(?User $user): bool
    {
        return (bool) $user?->can(self::PERMISSION);
    }

    /** Why this schedule cannot be approved because of a whole-day one, or null. */
    public static function problem(Schedule $schedule): ?string
    {
        if (! $schedule->start || ! $schedule->schedule_type_id) {
            return null;
        }

        [$from, $to] = static::days($schedule);

        $clash = Schedule::query()
            ->with('user:id,name')
            ->where('status', ScheduleStatus::Approved)
            ->where('schedule_type_id', $schedule->schedule_type_id)
            ->when($schedule->exists, fn($query) => $query->whereKeyNot($schedule->getKey()))
            // a timed schedule only clashes with whole-day ones; a whole-day one with anything
            ->when(! $schedule->all_day, fn($query) => $query->where('all_day', true))
            ->where('start', '<=', $to)
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$from])
            ->get()
            ->first(function (Schedule $other) use ($from, $to) {
                [$otherFrom, $otherTo] = static::days($other);

                return $otherFrom->lte($to) && $otherTo->gte($from);
            });

        if (! $clash) {
            return null;
        }

        return $clash->all_day
            ? __('A whole-day schedule is already approved that day (:name): nothing else of this type can be approved then.', ['name' => $clash->user?->name ?? '—'])
            : __('Other schedules of this type are already approved that day (:name): a whole-day schedule can only be approved on a day with nothing else approved.', ['name' => $clash->user?->name ?? '—']);
    }

    /**
     * The days a schedule takes, in the app's timezone.
     *
     * @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface}
     */
    private static function days(Schedule $schedule): array
    {
        $tz = config('app.timezone');
        $start = $schedule->start->copy()->setTimezone($tz);
        $end = $schedule->end?->copy()->setTimezone($tz);

        return [$start->copy()->startOfDay(), ($end && $end->gt($start) ? $end : $start)->copy()->endOfDay()];
    }
}
