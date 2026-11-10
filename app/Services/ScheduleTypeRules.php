<?php

namespace App\Services;

use App\Models\ScheduleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The rules a schedule type sets, in one place for the API, the admin panel and the PWA
 * (which gets them through the API, see toArray()):
 *
 * - start / end   when schedules of this type may exist (the first and last moment). Enforced.
 * - all_day       whether a schedule may be "all day". Enforced.
 * - range         whether a schedule needs an end time. Enforced.
 * - status        only active types take new schedules. Enforced.
 * - uses_slots    schedules take the times of one of its slots (shifts, ScheduleSlots). Enforced.
 * - min_time / max_time  the daily hours: a schedule with hours starts at min_time or later and
 *                 ends by max_time (a max of 00:00 = midnight). Enforced, for everyone. The
 *                 calendar shows exactly these hours, so nothing can fall outside it.
 */
class ScheduleTypeRules
{
    /** @var array<int, ScheduleType|null> types looked up during this request */
    private static array $cache = [];

    public function __construct(public readonly ScheduleType $type)
    {
    }

    /** The rules of a type by id (null when there is no such type), looked up once per request. */
    public static function for(int|string|null $typeId): ?self
    {
        if (! $typeId) {
            return null;
        }
        $type = self::$cache[(int) $typeId] ??= ScheduleType::find($typeId);

        return $type ? new self($type) : null;
    }

    /** Forget looked-up types (a type was just changed, or between tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    public function isActive(): bool
    {
        return (bool) $this->type->status;
    }

    /** First moment a schedule of this type may start, in the app's timezone. */
    public function windowStart(): ?CarbonInterface
    {
        return $this->type->start?->copy()->setTimezone(config('app.timezone'));
    }

    /** Last moment a schedule of this type may end, in the app's timezone. */
    public function windowEnd(): ?CarbonInterface
    {
        return $this->type->end?->copy()->setTimezone(config('app.timezone'));
    }

    /** Its last moment has passed: no new schedules (only people who may book the past record some afterwards). */
    public function hasEnded(): bool
    {
        $end = $this->windowEnd();

        return $end !== null && $end->lt(now());
    }

    public function allowsAllDay(): bool
    {
        return (bool) $this->type->all_day;
    }

    /** People pick one of its slots (shifts) instead of their own hours (ScheduleSlots). */
    public function usesSlots(): bool
    {
        return (bool) $this->type->uses_slots;
    }

    public function requiresEnd(): bool
    {
        return (bool) $this->type->range;
    }

    /**
     * Why a schedule with hours falls outside the type's daily hours, or null when it fits.
     * Times are compared on the clock, in the app's timezone; an end at midnight counts as 24:00.
     *
     * @return array{field: string, message: string}|null
     */
    public function hoursProblem(CarbonInterface $start, ?CarbonInterface $end): ?array
    {
        ['min' => $min, 'max' => $max] = $this->calendarHours();
        if (! $min && ! $max) {
            return null;
        }

        $tz = config('app.timezone');
        $start = $start->copy()->setTimezone($tz);
        $last = ($end ?? $start)->copy()->setTimezone($tz);

        $startClock = $start->format('H:i:s');
        $lastClock = $last->format('H:i:s');
        if ($lastClock === '00:00:00' && $last->gt($start)) {
            $lastClock = '24:00:00'; // ends at midnight
        }

        $hhmm = fn(string $time) => substr($time, 0, 5);
        $message = match (true) {
            $min && $max => __(':type: only between :from and :to.', ['type' => $this->type->name, 'from' => $hhmm($min), 'to' => $hhmm($max)]),
            (bool) $min => __(':type: not before :from.', ['type' => $this->type->name, 'from' => $hhmm($min)]),
            default => __(':type: only until :to.', ['type' => $this->type->name, 'to' => $hhmm($max)]),
        };

        if ($min && $startClock < $min) {
            return ['field' => 'start', 'message' => $message];
        }
        // a start after the end of the day, or an end after it
        if ($max && ($startClock >= $max || $lastClock > $max)) {
            return ['field' => $end ? 'end' : 'start', 'message' => $message];
        }

        return null;
    }

    /**
     * Hours the calendar shows for this type ("HH:mm:ss", null = no limit): its daily hours.
     *
     * @return array{min: ?string, max: ?string}
     */
    public function calendarHours(): array
    {
        return [
            'min' => $this->type->min_time?->format('H:i:s'),
            'max' => self::maxTime($this->type->max_time?->format('H:i:s')),
        ];
    }

    /**
     * Hours a calendar showing several types needs: from the earliest start to the latest end,
     * so no type is cut off. A type without a limit means no limit.
     *
     * @param iterable<ScheduleType> $types
     * @return array{min: ?string, max: ?string}
     */
    public static function calendarHoursFor(iterable $types): array
    {
        $hours = collect($types)->map(fn(ScheduleType $type) => (new self($type))->calendarHours());
        if ($hours->isEmpty()) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => $hours->contains('min', null) ? null : $hours->min('min'),
            'max' => $hours->contains('max', null) ? null : $hours->max('max'),
        ];
    }

    /** The active types, as the forms and calendars offer them (primary first). */
    public static function activeTypes(): Collection
    {
        return ScheduleType::query()->where('status', true)->orderByDesc('primary')->orderBy('name')->get();
    }

    /** For apps (the PWA): the same rules, as data. */
    public function toArray(): array
    {
        $hours = $this->calendarHours();

        return [
            'id' => $this->type->id,
            'name' => $this->type->name,
            'color' => $this->type->color,
            'description' => $this->type->description,
            'range' => $this->requiresEnd(),
            'all_day' => $this->allowsAllDay(),
            // pick a slot (GET schedules/slots) instead of giving hours
            'uses_slots' => $this->usesSlots(),
            'start' => $this->type->start,
            'end' => $this->type->end,
            // its end has passed: the PWA offers it only to people who may book the past
            'ended' => $this->hasEnded(),
            // the daily hours (enforced; also the calendar's visible hours)
            'min_time' => $hours['min'] ? substr($hours['min'], 0, 5) : null,
            'max_time' => $hours['max'] ? substr($hours['max'], 0, 5) : null,
        ];
    }

    /** A max of 00:00 means "until midnight" (FullCalendar wants 24:00:00 for that). */
    private static function maxTime(?string $time): ?string
    {
        return $time === '00:00:00' ? '24:00:00' : $time;
    }
}
