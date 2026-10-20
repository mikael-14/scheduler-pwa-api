<?php

namespace App\Console\Commands;

use App\Services\CoverageChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Warns everyone about days without anyone approved for a schedule type (config/coverage.php).
 * Runs daily from the scheduler; by hand it is handy to try rules out:
 *
 *   php artisan coverage:check --dry-run                 what would be sent today
 *   php artisan coverage:check --date=2026-11-01 --dry-run   as if it were that day
 *   php artisan coverage:check --rule=walks --days=3     one rule, 3 days ahead
 */
class CheckCoverage extends Command
{
    protected $signature = 'coverage:check
        {--date= : Check as if today were this day (YYYY-MM-DD)}
        {--rule=* : Only these rules (keys of coverage.rules)}
        {--days= : Days ahead to check, comma separated (default: each rule\'s notify_days_before)}
        {--dry-run : Show what would be sent, send nothing}
        {--force : Run even when COVERAGE_ALERTS is off}';

    protected $description = 'Warn everyone about days with nobody approved for a schedule type (config/coverage.php)';

    public function handle(CoverageChecker $checker): int
    {
        if (! config('coverage.enabled') && ! $this->option('force')) {
            $this->info('Coverage alerts are off (COVERAGE_ALERTS=false). Use --force to run anyway.');

            return self::SUCCESS;
        }

        if (! config('coverage.rules')) {
            $this->info('No coverage rules in config/coverage.php.');

            return self::SUCCESS;
        }

        $today = $this->option('date')
            ? Carbon::parse($this->option('date'), config('app.timezone'))
            : Carbon::now(config('app.timezone'));
        $days = $this->option('days')
            ? array_map('intval', explode(',', $this->option('days')))
            : null;

        $results = $checker->run($today, $this->option('rule'), $days, (bool) $this->option('dry-run'));

        $this->table(
            ['Rule', 'Type', 'Day', 'Days before', 'Approved', 'Pending', 'Result', 'Recipients'],
            array_map(fn(array $row) => [
                $row['rule'], $row['type'], $row['date'], $row['days_before'],
                $row['approved'], $row['pending'], $row['result'], $row['recipients'] ?: '',
            ], $results)
        );

        return self::SUCCESS;
    }
}
