<?php

namespace App\Services;

use App\Models\ScheduleType;
use App\Models\ScheduleTypeSlot;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The slots of a schedule type as they are defined (a day, a start and an end time, an optional
 * maximum and a comment): the checks and the saving shared by the admin panel and the API.
 * Taking a slot is ScheduleSlots.
 */
class SlotDefinitions
{
    /**
     * A day and two times as start / end, in the app's timezone (an end of 00:00 is midnight).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function moments(string $day, string $from, string $to): array
    {
        $tz = config('app.timezone');
        $date = Carbon::parse($day, $tz)->format('Y-m-d');
        $start = Carbon::parse($date . ' ' . substr($from, 0, 5), $tz);
        $end = Carbon::parse($date . ' ' . substr($to, 0, 5), $tz);
        if ($end->lte($start) && $end->format('H:i') === '00:00') {
            $end->addDay(); // until midnight
        }

        return [$start, $end];
    }

    /** A stored slot as a day and two times. */
    public static function toRow(ScheduleTypeSlot $slot): array
    {
        $tz = config('app.timezone');
        $start = $slot->start->copy()->setTimezone($tz);
        $end = $slot->end->copy()->setTimezone($tz);

        return ['day' => $start->format('Y-m-d'), 'from' => $start->format('H:i'), 'to' => $end->format('H:i')];
    }

    /** Why a slot does not fit (the end before the start, the type's dates or daily hours), or null. */
    public static function fitProblem(ScheduleType $type, Carbon $start, Carbon $end): ?string
    {
        if ($end->lte($start)) {
            return __('The end must be after the start.');
        }

        $rules = new ScheduleTypeRules($type);
        if (($windowStart = $rules->windowStart()) && $start->lt($windowStart)) {
            return __('This schedule type is only available from :date.', ['date' => $windowStart->format(config('app.date_time_format'))]);
        }
        if (($windowEnd = $rules->windowEnd()) && $end->gt($windowEnd)) {
            return __('This schedule type is only available until :date.', ['date' => $windowEnd->format(config('app.date_time_format'))]);
        }

        return $rules->hoursProblem($start, $end)['message'] ?? null;
    }

    /**
     * Makes the type's slots the given rows (from the API): a row with an id changes that slot,
     * one without is a new slot, a slot left out is removed. A slot with a schedule going on keeps
     * its times and can't be removed. Checked against the type as it is (call before saving it,
     * then save() after it is stored).
     *
     * @param  array<int, array{id?: int|null, day: string, from: string, to: string, capacity?: int|null, comment?: string|null}>  $rows
     * @return array<int, array{slot: ScheduleTypeSlot|null, attributes: array}> what save() stores
     *
     * @throws ValidationException
     */
    public static function check(ScheduleType $type, array $rows): array
    {
        $existing = $type->exists ? $type->slots()->get()->keyBy('id') : collect();
        $errors = [];
        $plan = [];
        $kept = [];

        foreach (array_values($rows) as $i => $row) {
            $slot = null;
            if (filled($row['id'] ?? null)) {
                $slot = $existing->get((int) $row['id']);
                if (! $slot) {
                    $errors["slots.$i.id"] = __('This slot belongs to another schedule type.');
                    continue;
                }
                $kept[] = $slot->id;
            }

            [$start, $end] = self::moments($row['day'], $row['from'], $row['to']);
            $attributes = [
                'capacity' => filled($row['capacity'] ?? null) ? (int) $row['capacity'] : null,
                'comment' => filled($row['comment'] ?? null) ? trim($row['comment']) : null,
            ];

            $moved = ! $slot || ! $slot->start->equalTo($start) || ! $slot->end->equalTo($end);
            if ($slot && $moved && $slot->isTaken()) {
                $errors["slots.$i.from"] = __('A slot with people on it keeps its times.');
                continue;
            }
            if ($moved) {
                if ($problem = self::fitProblem($type, $start, $end)) {
                    $errors["slots.$i.to"] = $problem;
                    continue;
                }
                $attributes += ['start' => $start, 'end' => $end];
            }

            $plan[] = ['slot' => $slot, 'attributes' => $attributes];
        }

        foreach ($existing->except($kept) as $gone) {
            if ($gone->isTaken()) {
                $errors['slots'] = __('A slot with people on it cannot be removed (:slot).', [
                    'slot' => $gone->start->copy()->setTimezone(config('app.timezone'))->format(config('app.date_format') . ' H:i'),
                ]);
            } else {
                $plan[] = ['slot' => $gone, 'attributes' => null];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $plan;
    }

    /** Stores what check() planned, on the saved type. */
    public static function save(ScheduleType $type, array $plan): void
    {
        foreach ($plan as ['slot' => $slot, 'attributes' => $attributes]) {
            if ($attributes === null) {
                $slot->delete();
            } elseif ($slot) {
                $slot->update($attributes);
            } else {
                $type->slots()->create($attributes);
            }
        }
    }
}
