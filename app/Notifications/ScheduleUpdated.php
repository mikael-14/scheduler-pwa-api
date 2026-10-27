<?php

namespace App\Notifications;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Sent to the owner and participants when someone else changes a schedule's time, type or
 * description, saying what changed ("When: 22/10 10:00–12:00 → 23/10 14:00–16:00").
 *
 * The before/after values are taken when the change happens: the queued email is written later,
 * when the schedule may have changed again. They are formatted when sent, in each person's language.
 */
class ScheduleUpdated extends AppNotification
{
    /** @var array<string, array{0: mixed, 1: mixed}> field => [before, after] */
    private readonly array $changes;

    private readonly bool $backToPending;

    public function __construct(private readonly Schedule $schedule, array $changedFields)
    {
        $this->changes = static::captureChanges($schedule, $changedFields);

        $this->backToPending = $schedule->wasChanged('status') && $schedule->status === ScheduleStatus::Pending;
    }

    /**
     * The before / after values of a change, taken while the model still knows both
     * (also used by ScheduleActivity).
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function captureChanges(Schedule $schedule, array $changedFields): array
    {
        $changes = [];
        foreach ($changedFields as $field) {
            $changes[$field] = [$schedule->getOriginal($field), $schedule->getAttribute($field)];
        }
        // the slot is shown as a whole: start, end and all-day together
        if (array_intersect($changedFields, ['start', 'end', 'all_day'])) {
            $changes['slot'] = [
                [$schedule->getOriginal('start'), $schedule->getOriginal('end'), (bool) $schedule->getOriginal('all_day')],
                [$schedule->start, $schedule->end, (bool) $schedule->all_day],
            ];
        }

        return $changes;
    }

    /**
     * "When: … → …", "Type: … → …", in the current language.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changes from captureChanges()
     * @return list<string>
     */
    public static function changeLines(array $changes): array
    {
        $lines = [];

        if (isset($changes['slot'])) {
            [$before, $after] = $changes['slot'];
            $lines[] = __('When: :before → :after', ['before' => static::slotText(...$before), 'after' => static::slotText(...$after)]);
        }
        if (isset($changes['schedule_type_id'])) {
            [$before, $after] = $changes['schedule_type_id'];
            $lines[] = __('Type: :before → :after', [
                'before' => ScheduleType::find($before)?->name ?? '—',
                'after' => ScheduleType::find($after)?->name ?? '—',
            ]);
        }
        if (isset($changes['description'])) {
            $lines[] = __('The description was changed.');
        }

        return $lines;
    }

    protected function title(): string
    {
        return __('A schedule you are in was changed');
    }

    protected function body(): string
    {
        $lines = [implode(' · ', array_filter([
            $this->schedule->schedule_type?->name,
            $this->schedule->start?->format(config('app.date_time_format')),
        ]))];

        $lines = [...$lines, ...static::changeLines($this->changes)];

        if ($this->backToPending) {
            $lines[] = __('It is waiting for approval again.');
        }

        return implode("\n", $lines);
    }

    protected function path(): string
    {
        return "/schedules/{$this->schedule->id}";
    }

    /** "22/10 10:00–12:00", "22/10 – 24/10 (no time)", in the app's date format. */
    private static function slotText(mixed $start, mixed $end, bool $allDay): string
    {
        $start = $start ? Carbon::parse($start) : null;
        $end = $end ? Carbon::parse($end) : null;
        if (! $start) {
            return '—';
        }

        $dateFormat = config('app.date_format', 'Y-m-d');

        if ($allDay) {
            $text = $start->format($dateFormat);
            if ($end && ! $end->isSameDay($start)) {
                $text .= ' – ' . $end->format($dateFormat);
            }

            return $text . ' (' . __('no time') . ')';
        }

        $text = $start->format(config('app.date_time_format'));
        if ($end instanceof CarbonInterface) {
            $text .= '–' . ($end->isSameDay($start) ? $end->format('H:i') : $end->format(config('app.date_time_format')));
        }

        return $text;
    }
}
