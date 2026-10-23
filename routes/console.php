<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled tasks. They only run when the scheduler is called every minute:
 *   - Linux:   * * * * * cd /path/to/scheduler-api && php artisan schedule:run >> /dev/null 2>&1
 *   - Windows: Task Scheduler, every minute: php artisan schedule:run (start in the project folder)
 *   - Dev:     php artisan schedule:work
 */

// API tokens past their expiry (30 days, or a renewed token's short grace period) are useless:
// delete those expired for more than a day, so personal_access_tokens does not grow forever.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Shared hosting (cPanel) has no Supervisor to keep `queue:work` running: with
// QUEUE_WORK_VIA_SCHEDULER=true the cron that calls schedule:run every minute also sends the queued
// emails and pushes, so they leave within a minute. Leave it off where a real worker runs.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --backoff=60')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn() => (bool) config('queue.work_via_scheduler'));

// Days with nobody approved for a schedule type: warn everyone a week and a day before
// (rules and on/off switch in config/coverage.php)
Schedule::command('coverage:check')
    ->dailyAt(config('coverage.run_at', '09:00'))
    ->timezone(config('app.timezone'))
    ->when(fn() => (bool) config('coverage.enabled'));
