<?php

namespace App\Notifications;

use App\Models\ScheduleType;
use Illuminate\Support\Carbon;

/**
 * "Nobody yet for Abrigo": the coming days without enough approved schedules of a type, one
 * summary per run and level (config/coverage.php, sent by `coverage:check`):
 *
 * - urgent (danger): the next `urgent_days` days, e.g. tomorrow
 * - look-ahead (warning): the days after those, up to `look_ahead_days`
 *
 * Push, email and the panel's bell show the days as a list; the PWA shows a link per day to the
 * new schedule form on that type and day (data.links), so volunteering takes one tap. The
 * notification itself opens the soonest day.
 */
class CoverageAlert extends AppNotification
{
    public const URGENT = 'urgent';

    public const AHEAD = 'ahead';

    /** The urgent push leaves this long after the look-ahead one (see FilamentNotificationObserver). */
    public const PUSH_GAP_SECONDS = 30;

    /**
     * @param list<array{date: string, days_before: int, pending: int}> $days  the uncovered days
     *        ("YYYY-MM-DD", how many days ahead, requests waiting), soonest first
     */
    public function __construct(
        private readonly ScheduleType $type,
        private readonly string $level,
        private readonly array $days,
    ) {
    }

    /**
     * The look-ahead one can wait for the email summary (see AppNotification::digestible); the
     * urgent one cannot: a morning summary would arrive on the day itself.
     */
    protected function digestible(): bool
    {
        return ! $this->urgent();
    }

    protected function title(): string
    {
        if ($this->urgent()) {
            return count($this->days) === 1 && $this->days[0]['days_before'] === 1
                ? __('Urgent: nobody yet for :type tomorrow', ['type' => $this->type->name])
                : __('Urgent: nobody yet for :type', ['type' => $this->type->name]);
        }

        return trans_choice('Nobody yet for :type on :count day|Nobody yet for :type on :count days', count($this->days), ['type' => $this->type->name]);
    }

    protected function body(): string
    {
        return implode("\n", [
            $this->intro(),
            ...array_map(fn(array $day) => '• ' . $this->dayLabel($day), $this->days),
            __('Can you help? Book it in the app.'),
        ]);
    }

    protected function status(): string
    {
        return $this->urgent() ? 'danger' : 'warning';
    }

    /**
     * With the soonest day it is about: one run sends its summaries in the same second, and the
     * lists show those by day, urgent first (User::notifications, SendEmailDigests). The PWA shows
     * `summary` and a link per day instead of the full list in the body.
     */
    public function toDatabase(object $notifiable): array
    {
        return parent::toDatabase($notifiable) + [
            'sort_day' => $this->days[0]['date'],
            'level' => $this->level,
            'summary' => $this->intro() . "\n" . __('Can you help? Tap a day to book it.'),
            'links' => array_map(fn(array $day) => [
                'label' => $this->dayLabel($day),
                'path' => $this->dayPath($day['date']),
            ], $this->days),
        ];
    }

    /** The new schedule form, already on this type and the soonest day. */
    protected function path(): string
    {
        return $this->dayPath($this->days[0]['date']);
    }

    private function urgent(): bool
    {
        return $this->level === self::URGENT;
    }

    private function intro(): string
    {
        return trans_choice('Nobody is approved yet for this day:|Nobody is approved yet for these days:', count($this->days));
    }

    private function dayPath(string $date): string
    {
        return '/schedules/new?' . http_build_query(['type' => $this->type->id, 'start' => $date]);
    }

    /** "terça-feira, 24 de novembro (amanhã) · 2 a aguardar aprovação", in the recipient's language. */
    private function dayLabel(array $day): string
    {
        $label = Carbon::parse($day['date'])->locale(app()->getLocale())->translatedFormat(__('l, j F'));

        if ($day['days_before'] === 1) {
            $label = __(':day (tomorrow)', ['day' => $label]);
        }

        if ($day['pending'] > 0) {
            $label .= ' · ' . trans_choice(':count request waiting for approval|:count requests waiting for approval', $day['pending']);
        }

        return $label;
    }
}
