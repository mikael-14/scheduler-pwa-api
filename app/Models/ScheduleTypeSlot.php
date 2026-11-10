<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A fixed time of a schedule type that uses slots (shifts): people pick it instead of choosing
 * their own hours. The first one creates its schedule, the next ones join it (ScheduleSlots).
 *
 * @property int $id
 * @property int $schedule_type_id
 * @property Carbon $start
 * @property Carbon $end
 * @property int|null $capacity  null = no limit
 * @property string|null $comment
 *
 * @property ScheduleType $schedule_type
 */
class ScheduleTypeSlot extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'schedule_type_slots';

    protected $casts = [
        'schedule_type_id' => 'int',
        'start' => 'datetime',
        'end' => 'datetime',
        'capacity' => 'int',
    ];

    protected $fillable = [
        'schedule_type_id',
        'start',
        'end',
        'capacity',
        'comment',
    ];

    public function schedule_type()
    {
        return $this->belongsTo(ScheduleType::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /** Its schedule while it is on (pending or approved): there is at most one. */
    public function activeSchedule(): ?Schedule
    {
        return $this->schedules()
            ->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Approved])
            ->latest('id')
            ->first();
    }

    /** People on it: the owner of its schedule and the participants who did not cancel. */
    public function peopleCount(?Schedule $schedule = null): int
    {
        $schedule ??= $this->activeSchedule();

        return $schedule ? static::peopleOn($schedule) : 0;
    }

    public function isFull(?Schedule $schedule = null): bool
    {
        return $this->capacity !== null && $this->peopleCount($schedule) >= $this->capacity;
    }

    /** The owner plus the participants still going (cancelled ones are soft deleted). */
    public static function peopleOn(Schedule $schedule): int
    {
        $participants = $schedule->relationLoaded('schedule_users')
            ? $schedule->schedule_users->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled])->count()
            : $schedule->schedule_users()->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled])->count();

        return ($schedule->user_id ? 1 : 0) + $participants;
    }

    /** It has a schedule going on: its times are fixed and it can't be removed. */
    public function isTaken(): bool
    {
        return $this->exists && $this->activeSchedule() !== null;
    }
}
