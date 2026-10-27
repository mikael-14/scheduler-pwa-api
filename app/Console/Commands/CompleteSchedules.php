<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approved schedules whose time is over become completed, with their approved participants.
 * Nobody chooses "completed": only this task sets it. Not shown (set by an admin or the owner)
 * is left as it is, and so are pending ones (nobody decided on them).
 */
class CompleteSchedules extends Command
{
    protected $signature = 'schedules:complete';

    protected $description = 'Mark approved schedules that are over as completed';

    public function handle(): int
    {
        $count = 0;

        $this->finished(Schedule::query()->where('status', ScheduleStatus::Approved))
            ->with('schedule_users')
            ->chunkById(100, function ($schedules) use (&$count) {
                foreach ($schedules as $schedule) {
                    // one by one, so the audit trail records it (the observers ignore outcomes)
                    $schedule->update(['status' => ScheduleStatus::Completed]);
                    $schedule->schedule_users
                        ->where('status', ScheduleStatus::Approved)
                        ->each->update(['status' => ScheduleStatus::Completed]);
                    $count++;
                }
            });

        $this->info("{$count} schedule(s) completed.");

        return self::SUCCESS;
    }

    /**
     * Over: past their end (their start when they have none). Whole-day ones store their days at
     * midnight, so they are over once their last day has passed.
     */
    private function finished(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $over) use ($now) {
            $over->where(fn(Builder $timed) => $timed->where('all_day', false)
                ->whereRaw('COALESCE(`end`, `start`) <= ?', [$now]))
                ->orWhere(fn(Builder $wholeDay) => $wholeDay->where('all_day', true)
                    ->whereRaw('COALESCE(`end`, `start`) < ?', [$now->copy()->startOfDay()]));
        });
    }
}
