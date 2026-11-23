<?php

namespace App\Console\Commands;

use App\Services\CoverageChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Warns everyone about the coming days without anyone approved for a schedule type, in an urgent
 * and a look-ahead summary (config/coverage.php). Runs daily from the scheduler; by hand it is
 * handy to try rules out:
 *
 *   php artisan coverage:check --dry-run                       what would be sent today
 *   php artisan coverage:check --date=2026-11-01 --dry-run     as if it were that day (its first run)
 *   php artisan coverage:check --date=2026-11-01 --at=18:00 --dry-run   that day's 18:00 run
 *   php artisan coverage:check --rule=walks --look-ahead=14 --urgent=3 --dry-run
 */
class CheckCoverage extends Command
{
    protected $signature = 'coverage:check
        {--date= : Check as if today were this day (YYYY-MM-DD)}
        {--at= : Which daily run this is, HH:MM (default: the latest run time reached now; with --date, the first one)}
        {--rule=* : Only these rules (keys of coverage.rules)}
        {--look-ahead= : Days ahead to check (default: each rule\'s look_ahead_days)}
        {--urgent= : The first days ahead that are urgent (default: each rule\'s urgent_days)}
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
        $number = fn(string $option) => $this->option($option) === null ? null : (int) $this->option($option);

        $at = $this->option('at');
        if ($at !== null && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $at)) {
            $this->error('--at must be a time like 09:00.');

            return self::FAILURE;
        }
        $at ??= $this->option('date') ? CoverageChecker::runTimes()[0] : CoverageChecker::runAt($today);
        $this->line("Run of {$today->toDateString()} {$at}");

        $results = $checker->run($today, $this->option('rule'), $number('look-ahead'), $number('urgent'), (bool) $this->option('dry-run'), $at);

        $this->table(
            ['Rule', 'Type', 'Day', 'Days ahead', 'Summary', 'Approved', 'Pending', 'Result', 'Recipients'],
            array_map(fn(array $row) => [
                $row['rule'], $row['type'], $row['date'], $row['days_before'], $row['level'],
                $row['approved'], $row['pending'], $row['result'], $row['recipients'] ?: '',
            ], $results)
        );

        return self::SUCCESS;
    }
}
