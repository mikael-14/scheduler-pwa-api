<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\CoverageAlert;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * Finds the coming days without enough approved schedules of a type and warns everyone with (at
 * most) two summaries per rule: urgent and look-ahead (config/coverage.php). Used by
 * `php artisan coverage:check`.
 */
class CoverageChecker
{
    /**
     * The daily run times (coverage.run_at): "HH:MM", once each, earliest first. Accepts a list or a
     * comma separated string; anything that is not a time is left out. At least 09:00.
     *
     * @return list<string>
     */
    public static function runTimes(): array
    {
        $configured = config('coverage.run_at', '09:00');
        $times = is_array($configured) ? $configured : explode(',', (string) $configured);

        $times = collect($times)
            ->map(fn($time) => trim((string) $time))
            ->filter(fn(string $time) => preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time))
            ->map(fn(string $time) => str_pad($time, 5, '0', STR_PAD_LEFT))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $times ?: ['09:00'];
    }

    /**
     * Which run a moment belongs to: the latest run time already reached that day, "HH:MM"
     * ("00:00" before the first one, e.g. a check started by hand early in the morning).
     */
    public static function runAt(CarbonInterface $moment): string
    {
        $now = $moment->format('H:i');

        return collect(self::runTimes())->filter(fn(string $time) => $time <= $now)->last() ?? '00:00';
    }

    /**
     * Check every rule (or only `$onlyRules`) for the next days after `$today`, as the run of `$at`
     * ("HH:MM", default: the run `$today`'s time belongs to, see runAt()). Every run sends its own
     * summaries; the same run started again sends nothing new.
     *
     * @param list<string> $onlyRules  rule keys; empty = all
     * @param int|null $lookAhead  overrides each rule's look_ahead_days
     * @param int|null $urgent  overrides each rule's urgent_days
     * @return list<array{rule: string, type: string, date: string, days_before: int, level: string, approved: int, pending: int, result: string, recipients: int}>
     *         one row per day checked, soonest first
     */
    public function run(CarbonInterface $today, array $onlyRules = [], ?int $lookAhead = null, ?int $urgent = null, bool $dryRun = false, ?string $at = null): array
    {
        $results = [];
        $at ??= self::runAt($today);
        $today = $today->copy()->startOfDay();

        foreach ($this->rules($onlyRules) as $key => $rule) {
            $type = $this->type($rule['type'] ?? null);
            $urgentDays = max(0, (int) ($urgent ?? $rule['urgent_days'] ?? 0));
            $lastDay = max($urgentDays, (int) ($lookAhead ?? $rule['look_ahead_days'] ?? 0));
            $rows = [];

            for ($days = 1; $days <= $lastDay; $days++) {
                $date = $today->copy()->addDays($days);
                $row = ['rule' => $key, 'type' => $type?->name ?? (string) ($rule['type'] ?? '?'), 'date' => $date->toDateString(),
                    'days_before' => $days, 'level' => $days <= $urgentDays ? CoverageAlert::URGENT : CoverageAlert::AHEAD,
                    'approved' => 0, 'pending' => 0, 'result' => '', 'recipients' => 0];

                $row['result'] = match (true) {
                    ! $type => 'type not found',
                    ! $type->status => 'type inactive',
                    ! in_array($date->dayOfWeekIso, $rule['weekdays'], true) => 'weekday not checked',
                    ! $this->insideWindow($type, $date) => 'outside the type\'s dates',
                    default => '',
                };

                if ($row['result'] === '') {
                    [$row['approved'], $row['pending']] = $this->counts($type, $date);
                    $row['result'] = $row['approved'] >= $rule['min_approved'] ? 'covered' : 'uncovered';
                }

                $rows[] = $row;
            }

            // look-ahead first, urgent last: on a phone the last push is on top (the lists in the
            // app put the urgent one first themselves, by its soonest day, see User::notifications)
            foreach ([CoverageAlert::AHEAD, CoverageAlert::URGENT] as $level) {
                $uncovered = array_keys(array_filter($rows, fn(array $row) => $row['result'] === 'uncovered' && $row['level'] === $level));
                if (! $uncovered) {
                    continue;
                }

                $days = array_map(fn(int $i) => $rows[$i], $uncovered);
                $recipients = null;

                if ($this->alreadySent($key, $level, $today, $at)) {
                    $result = 'already sent';
                } else {
                    $recipients = $this->audience($rule['audience']);
                    $result = $dryRun ? 'would send' : 'sent';

                    if (! $dryRun) {
                        $this->send($key, $type, $level, $today, $at, $days, $recipients);
                    }
                }

                foreach ($uncovered as $i) {
                    $rows[$i]['result'] = $result;
                    $rows[$i]['recipients'] = $recipients?->count() ?? 0;
                }
            }

            array_push($results, ...$rows);
        }

        return $results;
    }

    /** The configured rules with the defaults filled in. */
    public function rules(array $only = []): array
    {
        $defaults = config('coverage.defaults', []);

        return collect(config('coverage.rules', []))
            ->when($only !== [], fn($rules) => $rules->only($only))
            ->map(fn(array $rule) => array_merge($defaults, $rule))
            ->all();
    }

    private function type(mixed $nameOrId): ?ScheduleType
    {
        return is_int($nameOrId)
            ? ScheduleType::find($nameOrId)
            : ScheduleType::where('name', $nameOrId)->first();
    }

    /** The day is inside the type's start / end (when it may be booked). */
    private function insideWindow(ScheduleType $type, CarbonInterface $date): bool
    {
        $rules = new ScheduleTypeRules($type);
        $start = $rules->windowStart();
        $end = $rules->windowEnd();

        return (! $start || $date->copy()->endOfDay()->gte($start))
            && (! $end || $date->copy()->startOfDay()->lte($end));
    }

    /** Approved and pending schedules of the type touching the day (all-day and multi-day included). */
    private function counts(ScheduleType $type, CarbonInterface $date): array
    {
        $onDay = fn(Builder $query) => $query
            ->where('schedule_type_id', $type->id)
            ->where('start', '<=', $date->copy()->endOfDay())
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$date->copy()->startOfDay()]);

        return [
            Schedule::query()->tap($onDay)->where('status', ScheduleStatus::Approved)->count(),
            Schedule::query()->tap($onDay)->where('status', ScheduleStatus::Pending)->count(),
        ];
    }

    /** Each summary once per run: this rule's urgent / look-ahead one went out already today at `$at`. */
    private function alreadySent(string $rule, string $level, CarbonInterface $today, string $at): bool
    {
        return DB::table('coverage_alerts')
            ->where(['rule' => $rule, 'level' => $level, 'run_at' => $at])
            ->whereDate('sent_on', $today->toDateString())
            ->exists();
    }

    /**
     * 'everyone' or ['roles' => [...]]; always only accounts that can use the app and book
     * (`create_schedule`): the alert asks them to book, so e.g. an Externo, who only joins, is not told.
     */
    private function audience(mixed $audience)
    {
        if (! Permission::where('name', 'create_schedule')->exists()) {
            return collect();
        }

        return User::permission('create_schedule')
            ->where('status', true)
            ->whereNotNull('approved_at')
            ->when(is_array($audience) && ! empty($audience['roles']), fn($query) => $query->role($audience['roles']))
            ->get()
            ->unique('id')
            ->values();
    }

    /** @param list<array> $days the summary's rows, soonest first */
    private function send(string $rule, ScheduleType $type, string $level, CarbonInterface $today, string $at, array $days, $recipients): void
    {
        // recorded first: a mail hiccup halfway must not make the next run warn everyone again
        DB::table('coverage_alerts')->insertOrIgnore(array_map(fn(array $day) => [
            'rule' => $rule,
            'schedule_type_id' => $type->id,
            'date' => $day['date'],
            'days_before' => $day['days_before'],
            'level' => $level,
            'sent_on' => $today->toDateString(),
            'run_at' => $at,
            'approved_count' => $day['approved'],
            'pending_count' => $day['pending'],
            'recipients' => $recipients->count(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $days));

        $summary = array_map(fn(array $day) => ['date' => $day['date'], 'days_before' => $day['days_before'], 'pending' => $day['pending']], $days);

        Notification::send($recipients, new CoverageAlert($type, $level, $summary));
    }
}
