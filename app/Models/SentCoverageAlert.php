<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of a coverage summary that was sent (the coverage_alerts table, written by
 * CoverageChecker): which rule, the uncovered day, the summary it was in (`level`: urgent or ahead),
 * the morning it went out (`sent_on`) and the counts at that moment. Read-only history, shown on
 * the admin panel's Coverage alerts page.
 */
class SentCoverageAlert extends Model
{
    protected $table = 'coverage_alerts';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sent_on' => 'date',
            'days_before' => 'integer',
            'approved_count' => 'integer',
            'pending_count' => 'integer',
            'recipients' => 'integer',
        ];
    }

    public function scheduleType(): BelongsTo
    {
        return $this->belongsTo(ScheduleType::class);
    }
}
