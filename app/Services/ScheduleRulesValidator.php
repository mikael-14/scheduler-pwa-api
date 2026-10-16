<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleType;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the rules of a schedule type (date window, daily hours, range, all-day, active)
 * and the "no past schedules" rule on the model, so the admin panel, the calendar
 * modals and the API all obey them, not just the Filament form.
 *
 * Only runs when something that affects the slot changes, so approving or
 * annotating an old schedule is never blocked.
 */
class ScheduleRulesValidator
{
    private const SLOT_FIELDS = ['start', 'end', 'all_day', 'schedule_type_id'];

    /**
     * Called from the model's `saving` hook.
     * In the admin panel (Livewire) the problem is shown as a notification and the save is halted;
     * everywhere else a ValidationException (HTTP 422 on the API) is thrown.
     */
    public static function enforce(Schedule $schedule): void
    {
        try {
            static::validate($schedule);
        } catch (ValidationException $e) {
            if (request()->hasHeader('X-Livewire') && class_exists(Halt::class)) {
                Notification::make()
                    ->title(__('This schedule breaks the rules of its type'))
                    ->body(collect($e->errors())->flatten()->implode("\n"))
                    ->danger()
                    ->send();

                throw new Halt();
            }

            throw $e;
        }
    }

    /** @throws ValidationException */
    public static function validate(Schedule $schedule): void
    {
        if ($schedule->exists && ! $schedule->isDirty(self::SLOT_FIELDS)) {
            return;
        }

        $errors = [];
        $tz = config('app.timezone');
        $start = $schedule->start?->copy()->setTimezone($tz);
        $end = $schedule->end?->copy()->setTimezone($tz);

        if (! $start) {
            return; // the required-field rules report this
        }

        if ($end && $end->lt($start)) {
            $errors['end'][] = __('The end must be after the start.');
        }

        // Past schedules need the dedicated permission
        $actor = auth()->user();
        $startChanged = ! $schedule->exists || $schedule->isDirty('start');
        if ($actor && $startChanged && $start->lt(now($tz)->startOfDay()) && ! $actor->can('update_past_schedule')) {
            $errors['start'][] = __('You cannot schedule or move a schedule to a past date.');
        }

        $type = $schedule->schedule_type_id ? ScheduleType::find($schedule->schedule_type_id) : null;

        if ($type) {
            static::validateAgainstType($schedule, $type, $start, $end, $errors);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function validateAgainstType(
        Schedule $schedule,
        ScheduleType $type,
        CarbonInterface $start,
        ?CarbonInterface $end,
        array &$errors,
    ): void {
        $tz = config('app.timezone');
        $typeChanged = ! $schedule->exists || $schedule->isDirty('schedule_type_id');

        if (! $type->status && $typeChanged) {
            $errors['schedule_type_id'][] = __('This schedule type is not active.');
        }

        if ($type->range && ! $end) {
            $errors['end'][] = __('This schedule type requires an end time.');
        }

        if ($schedule->all_day && ! $type->all_day) {
            $errors['all_day'][] = __('This schedule type does not allow all-day schedules.');
        }

        // Date window of the type
        $windowStart = $type->start?->copy()->setTimezone($tz);
        $windowEnd = $type->end?->copy()->setTimezone($tz);
        $last = $end ?? $start;

        if ($windowStart && $start->lt($windowStart)) {
            $errors['start'][] = __('This schedule type is only available from :date.', [
                'date' => $windowStart->format(config('app.date_time_format')),
            ]);
        }
        if ($windowEnd && $last->gt($windowEnd)) {
            $errors['end'][] = __('This schedule type is only available until :date.', [
                'date' => $windowEnd->format(config('app.date_time_format')),
            ]);
        }

        // Daily hours (not applicable to all-day schedules)
        if ($schedule->all_day) {
            return;
        }

        $min = $type->min_time?->format('H:i:s');
        $max = $type->max_time?->format('H:i:s');
        // A max of 00:00 means "until midnight"
        if ($max === '00:00:00') {
            $max = '24:00:00';
        }

        if ($min && $start->format('H:i:s') < $min) {
            $errors['start'][] = __('This schedule type starts at :time.', ['time' => substr($min, 0, 5)]);
        }
        if ($max && ($end ?? $start)->format('H:i:s') > $max && ($end ?? $start)->format('H:i:s') !== '00:00:00') {
            $errors['end'][] = __('This schedule type ends at :time.', ['time' => substr($max, 0, 5)]);
        }
    }
}
