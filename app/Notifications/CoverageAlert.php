<?php

namespace App\Notifications;

use App\Models\ScheduleType;
use Illuminate\Support\Carbon;

/**
 * "Nobody yet for Walks on Saturday": a day without enough approved schedules of a type
 * (config/coverage.php, sent by `coverage:check`). Opens the PWA's new schedule form on that
 * type and day, so volunteering takes one tap.
 */
class CoverageAlert extends AppNotification
{
    public function __construct(
        private readonly ScheduleType $type,
        /** the uncovered day, "YYYY-MM-DD" */
        private readonly string $date,
        private readonly int $daysBefore,
        private readonly int $pending,
    ) {
    }

    protected function title(): string
    {
        return __('Nobody yet for :type on :date', ['type' => $this->type->name, 'date' => $this->dayName()]);
    }

    protected function body(): string
    {
        $lines = [
            $this->daysBefore === 1 ? __('It is tomorrow and nobody is approved yet.') : __('Nobody is approved for this day yet.'),
        ];
        if ($this->pending > 0) {
            $lines[] = __(':count request(s) waiting for approval.', ['count' => $this->pending]);
        }
        $lines[] = __('Can you help? Book it in the app.');

        return implode("\n", $lines);
    }

    protected function status(): string
    {
        return 'warning';
    }

    /** The new schedule form, already on this type and day. */
    protected function path(): string
    {
        return '/schedules/new?' . http_build_query(['type' => $this->type->id, 'start' => $this->date]);
    }

    /** "sábado, 25 de outubro", in the recipient's language (set by the sender). */
    private function dayName(): string
    {
        return Carbon::parse($this->date)->locale(app()->getLocale())->translatedFormat(__('l, j F'));
    }
}
