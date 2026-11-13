<?php

namespace App\Models;

use App\Services\AuditTags;
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

    /**
     * "Slot 21/11/2026 09:00–11:00" for an audit of a slot, also when the slot is gone
     * (then from the audit's values).
     */
    public static function auditLabel($audit): string
    {
        $value = fn(string $key) => $audit->auditable?->{$key}
            ?? $audit->new_values[$key]
            ?? $audit->old_values[$key]
            ?? null;
        $tz = config('app.timezone');

        try {
            $start = filled($value('start')) ? Carbon::parse($value('start'))->setTimezone($tz) : null;
            $end = filled($value('end')) ? Carbon::parse($value('end'))->setTimezone($tz) : null;
        } catch (\Throwable) {
            $start = $end = null;
        }

        return $start
            ? __('Slot') . ' ' . $start->format(config('app.date_format') . ' H:i') . ($end ? '–' . $end->format('H:i') : '')
            : __('Slot') . " #{$audit->auditable_id}";
    }

    /** The slot ids that ever belonged to the type: the ones there and the removed ones (from their audits). */
    public static function everOf(int $typeId): array
    {
        return static::where('schedule_type_id', $typeId)->pluck('id')
            ->merge(\OwenIt\Auditing\Models\Audit::query()
                ->where('auditable_type', static::class)
                ->where(fn($q) => $q
                    ->where('new_values->schedule_type_id', $typeId)
                    ->orWhere('old_values->schedule_type_id', $typeId))
                ->pluck('auditable_id'))
            ->unique()->values()->all();
    }

    /** It has a schedule going on: its times are fixed and it can't be removed. */
    public function isTaken(): bool
    {
        return $this->exists && $this->activeSchedule() !== null;
    }

    /** The audit's tags: a slot of which type. See AuditTags. */
    public function generateTags(): array
    {
        return AuditTags::make($this, [
            'slot',
            $this->schedule_type?->name,
            AuditTags::changed($this, 'start', 'end') ? 'times' : null,
            AuditTags::changed($this, 'capacity') ? 'capacity' : null,
            AuditTags::changed($this, 'comment') ? 'comment' : null,
        ]);
    }
}
