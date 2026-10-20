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

/**
 * Finds days without enough approved schedules of a type and warns everyone
 * (config/coverage.php). Used by `php artisan coverage:check`.
 */
class CoverageChecker
{
    /**
     * Check every rule (or only `$onlyRules`) for the days `notify_days_before` ahead of `$today`.
     *
     * @param list<string> $onlyRules  rule keys; empty = all
     * @param list<int>|null $daysBefore  overrides each rule's notify_days_before
     * @return list<array{rule: string, type: string, date: string, days_before: int, approved: int, pending: int, result: string, recipients: int}>
     */
    public function run(CarbonInterface $today, array $onlyRules = [], ?array $daysBefore = null, bool $dryRun = false): array
    {
        $results = [];

        foreach ($this->rules($onlyRules) as $key => $rule) {
            $type = $this->type($rule['type'] ?? null);

            foreach ($daysBefore ?? $rule['notify_days_before'] as $days) {
                $date = $today->copy()->startOfDay()->addDays((int) $days);
                $row = ['rule' => $key, 'type' => $type?->name ?? (string) ($rule['type'] ?? '?'), 'date' => $date->toDateString(),
                    'days_before' => (int) $days, 'approved' => 0, 'pending' => 0, 'result' => '', 'recipients' => 0];

                $row['result'] = match (true) {
                    ! $type => 'type not found',
                    ! $type->status => 'type inactive',
                    ! in_array($date->dayOfWeekIso, $rule['weekdays'], true) => 'weekday not checked',
                    ! $this->insideWindow($type, $date) => 'outside the type\'s dates',
                    default => '',
                };

                if ($row['result'] === '') {
                    [$row['approved'], $row['pending']] = $this->counts($type, $date);

                    if ($row['approved'] >= $rule['min_approved']) {
                        $row['result'] = 'covered';
                    } elseif ($this->alreadySent($key, $date, (int) $days)) {
                        $row['result'] = 'already sent';
                    } else {
                        $recipients = $this->audience($rule['audience']);
                        $row['recipients'] = $recipients->count();
                        $row['result'] = $dryRun ? 'would send' : 'sent';

                        if (! $dryRun) {
                            $this->send($key, $type, $date, (int) $days, $row, $recipients);
                        }
                    }
                }

                $results[] = $row;
            }
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

    private function alreadySent(string $rule, CarbonInterface $date, int $days): bool
    {
        return DB::table('coverage_alerts')
            ->where(['rule' => $rule, 'date' => $date->toDateString(), 'days_before' => $days])
            ->exists();
    }

    /** 'everyone' or ['roles' => [...]]; always only accounts that can use the app. */
    private function audience(mixed $audience)
    {
        return User::query()
            ->where('status', true)
            ->whereNotNull('approved_at')
            ->when(is_array($audience) && ! empty($audience['roles']), fn($query) => $query->role($audience['roles']))
            ->get();
    }

    private function send(string $rule, ScheduleType $type, CarbonInterface $date, int $days, array $row, $recipients): void
    {
        // recorded first: a mail hiccup halfway must not make the next run warn everyone again
        DB::table('coverage_alerts')->insert([
            'rule' => $rule,
            'schedule_type_id' => $type->id,
            'date' => $date->toDateString(),
            'days_before' => $days,
            'approved_count' => $row['approved'],
            'pending_count' => $row['pending'],
            'recipients' => $recipients->count(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::send($recipients, new CoverageAlert($type, $date->toDateString(), $days, $row['pending']));
    }
}
