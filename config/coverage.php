<?php

/*
|--------------------------------------------------------------------------
| Coverage alerts
|--------------------------------------------------------------------------
|
| Warn everyone when a day has nobody approved for a schedule type, a week before and the day
| before, so volunteers can still book it. Checked once a day by `php artisan coverage:check`
| (scheduled in routes/console.php; needs the scheduler running, see the README).
|
| A day is "uncovered" for a rule when it is one of the rule's weekdays, falls inside the type's
| start / end (when the type may be booked), and has fewer than `min_approved` APPROVED schedules
| of the type touching that day (pending ones do not count). Each alert is sent once per rule,
| day and "days before" (see the coverage_alerts table).
|
| Every rule takes the `defaults` below unless it sets its own value.
|
*/

return [

    // Master switch. COVERAGE_ALERTS=false in .env stops every alert (the command still runs with --force).
    'enabled' => env('COVERAGE_ALERTS', true),

    // When the daily check runs, in the app's timezone (Europe/Lisbon).
    'run_at' => env('COVERAGE_ALERTS_AT', '09:00'),

    'defaults' => [
        // ISO weekdays to check: 1 = Monday … 7 = Sunday
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],

        // fewer approved schedules than this on a day = uncovered (1: "nobody approved")
        'min_approved' => 1,

        // how many days ahead to warn: a week before and the day before
        'notify_days_before' => [7, 1],

        // who is told: 'everyone' (active, approved accounts) or ['roles' => ['volunteer', ...]]
        'audience' => 'everyone',
    ],

    /*
     | One entry per schedule type to watch. `type` is the type's name (names are the same in
     | every database, ids are not). Any default above can be set per rule. Examples:
     |
     |   'walks' => ['type' => 'Passeios'],
     |   'cleaning' => ['type' => 'Limpeza', 'weekdays' => [6], 'min_approved' => 2,
     |                  'audience' => ['roles' => ['volunteer']]],
     */
    'rules' => [
        // 'walks' => ['type' => 'Passeios'],
    ],

];
