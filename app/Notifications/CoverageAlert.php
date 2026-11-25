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

    /**
     * The urgent push waits this long (see FilamentNotificationObserver), so it leaves in a later
     * queue run than the look-ahead one. Over a minute on purpose: with QUEUE_WORK_VIA_SCHEDULER the
     * queue is only worked once a minute, and a shorter wait has already passed by the next run,
     * which then sends both in the same second (some phones drop one of them).
     */
    public const PUSH_GAP_SECONDS = 90;

    /**
     * @param list<array{date: string, days_before: int, pending: int}> $days  the uncovered days
     *        ("YYYY-MM-DD", how many days ahead, requests waiting), soonest first
     * @param list<array{date: string, days_before: int, pending: int}> $later  urgent only: the
     *        look-ahead days of the same run, added to its email (one email with both)
     * @param bool $emailedWithUrgent  look-ahead only: its days are in the urgent email of the same
     *        run, so it sends no email of its own (nor waits for the summary)
     */
    public function __construct(
        private readonly ScheduleType $type,
        private readonly string $level,
        private readonly array $days,
        private readonly array $later = [],
        private readonly bool $emailedWithUrgent = false,
    ) {
    }

    public function emailWanted(object $notifiable): bool
    {
        return ! $this->emailedWithUrgent && parent::emailWanted($notifiable);
    }

    /** The days are the email's lists (mailSections), each with a button to book it. */
    protected function mailLines(): array
    {
        return [__('Can you help? Pick a day and book it in the app.')];
    }

    /** The urgent title already starts with "Urgent". */
    protected function mailKicker(): ?string
    {
        return $this->urgent() ? null : __('Look-ahead');
    }

    /** Its days; the urgent email also the look-ahead days of the same run (amber). */
    protected function mailSections(): array
    {
        $sections = [['heading' => $this->intro(), 'items' => $this->mailDays($this->days, $this->status())]];

        if ($this->later) {
            $sections[] = ['heading' => __('Also nobody yet in the coming days:'), 'items' => $this->mailDays($this->later, 'warning')];
        }

        return $sections;
    }

    private function mailDays(array $days, string $status): array
    {
        return array_map(fn(array $day) => [
            'label' => $this->dayName($day),
            'meta' => $day['pending'] > 0
                ? trans_choice(':count request waiting for approval|:count requests waiting for approval', $day['pending'])
                : null,
            'url' => $this->pwaUrl($this->dayPath($day['date'])),
            'link' => __('Book'),
            'status' => $status,
        ], $days);
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
        $label = $this->dayName($day);

        if ($day['pending'] > 0) {
            $label .= ' · ' . trans_choice(':count request waiting for approval|:count requests waiting for approval', $day['pending']);
        }

        return $label;
    }

    /** "terça-feira, 24 de novembro (amanhã)" */
    private function dayName(array $day): string
    {
        $label = Carbon::parse($day['date'])->locale(app()->getLocale())->translatedFormat(__('l, j F'));

        return $day['days_before'] === 1 ? __(':day (tomorrow)', ['day' => $label]) : $label;
    }
}
