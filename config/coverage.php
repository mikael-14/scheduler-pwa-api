<?php

/*
|--------------------------------------------------------------------------
| Coverage alerts
|--------------------------------------------------------------------------
|
| Warn everyone about the coming days that have nobody approved for a schedule type, so volunteers
| can still book them. Checked once a day by `php artisan coverage:check` (scheduled in
| routes/console.php; needs the scheduler running, see the README).
|
| A day is "uncovered" for a rule when it is one of the rule's weekdays, falls inside the type's
| start / end (when the type may be booked), and has fewer than `min_approved` APPROVED schedules
| of the type touching that day (pending ones do not count). The uncovered days of one run are
| sent as one urgent and one look-ahead summary per rule, each listing its days with a link to
| book each one (what was sent: the coverage_alerts table).
|
| Every rule takes the `defaults` below unless it sets its own value.
|
*/

return [

    // Master switch. COVERAGE_ALERTS=false in .env stops every alert (the command still runs with --force).
    'enabled' => env('COVERAGE_ALERTS', true),

    // When the check runs every day, in the app's timezone (Europe/Lisbon): one time or several.
    // Each run sends its own summaries (see below). In .env: COVERAGE_ALERTS_AT=09:00,18:00
    'run_at' => explode(',', (string) env('COVERAGE_ALERTS_AT', '09:00,18:00')),

    'defaults' => [
        // ISO weekdays to check: 1 = Monday … 7 = Sunday
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],

        // fewer approved schedules than this on a day = uncovered (1: "nobody approved")
        'min_approved' => 1,

        // At every run (run_at) the next `look_ahead_days` days are checked, tomorrow first. The
        // uncovered ones are sent as (at most) two summaries, one notification each, at every run
        // while they have a day (one run never sends twice, even when started again):
        //  - urgent (red): the uncovered days among the next `urgent_days`. 0 = no urgent summary.
        //  - look-ahead (amber): the uncovered days after those, up to `look_ahead_days`. 0 = none.
        'look_ahead_days' => 7,
        'urgent_days' => 2,

        // who is told: 'everyone' or ['roles' => ['volunteer', ...]]; always only active, approved
        // accounts that can book (create_schedule): an Externo, who only joins, is never told
        'audience' => 'everyone',
    ],

    /*
     | One entry per schedule type to watch. `type` is the type's name (names are the same in
     | every database, ids are not). Any default above can be set per rule. Examples:
     |
     |   'walks' => ['type' => 'Passeios'],
     |   'cleaning' => ['type' => 'Limpeza', 'weekdays' => [6], 'min_approved' => 2,
     |                  'look_ahead_days' => 14, 'urgent_days' => 3,
     |                  'audience' => ['roles' => ['volunteer']]],
     */
    'rules' => [
        'abrigo' => ['type' => 'Abrigo'],
        // 'walks' => ['type' => 'Passeios'],
    ],

];
