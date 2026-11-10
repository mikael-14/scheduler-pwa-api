<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Observers\ScheduleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use OwenIt\Auditing\Contracts\Auditable;
use Rupadana\ApiService\Contracts\HasAllowedFields;
use Rupadana\ApiService\Contracts\HasAllowedFilters;
use Rupadana\ApiService\Contracts\HasAllowedSorts;

/**
 * Class Schedule
 * 
 * @property int $id
 * @property Carbon $start
 * @property Carbon|null $end
 * @property bool $all_day
 * @property string|null $description
 * @property string|null $internal_note
 * @property string $status
 * @property int|null $user_id
 * @property int|null $schedule_type_id
 * @property int|null $schedule_type_slot_id  the slot (shift) it was booked on, for types that use slots
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property ScheduleType|null $schedule_type
 * @property User|null $user
 * @property Collection|User[] $users
 *
 * @package App\Models
 */
#[ObservedBy(ScheduleObserver::class)]
class Schedule extends Model implements Auditable, HasAllowedFields, HasAllowedFilters, HasAllowedSorts
{
	use SoftDeletes, \OwenIt\Auditing\Auditable;
	protected $table = 'schedules';

	protected $casts = [
		'start' => 'datetime',
		'end' => 'datetime',
		'all_day' => 'bool',
		'user_id' => 'int',
		'schedule_type_id' => 'int',
		'schedule_type_slot_id' => 'int',
		'status' => ScheduleStatus::class,
	];

	protected $fillable = [
		'start',
		'end',
		'all_day',
		'description',
		'internal_note',
		'status',
		'user_id',
		'schedule_type_id',
		'schedule_type_slot_id'
	];

	protected static function booted(): void
	{
		// Who may set which status, and when (ScheduleAccess::mayChangeStatus), on every path
		static::saving(fn(Schedule $schedule) => \App\Services\ScheduleAccess::enforceStatusChange($schedule));
		// Who owns it: booking for / handing over to someone else needs create_any / update_any_schedule
		static::saving(fn(Schedule $schedule) => \App\Services\ScheduleAccess::enforceOwner($schedule));
		// Moved to a type without slots (shifts): it is no longer on a slot
		static::saving(function (Schedule $schedule) {
			if ($schedule->schedule_type_slot_id && $schedule->isDirty('schedule_type_id')
				&& ! \App\Services\ScheduleTypeRules::for($schedule->schedule_type_id)?->usesSlots()) {
				$schedule->schedule_type_slot_id = null;
			}
		});
		// Type rules and the past-date rule must hold on every path (panel, calendar modals, API)
		static::saving(fn(Schedule $schedule) => \App\Services\ScheduleRulesValidator::enforce($schedule));
		// Primary type: who may be approved without someone who may go alone
		static::saving(fn(Schedule $schedule) => \App\Services\PrimaryScheduleRule::enforceOnSchedule($schedule));
		// Handed over to one of its participants: they are the owner now, not also a participant
		// (removed for good, also a cancelled participation: the owner never shows as cancelled)
		static::updated(function (Schedule $schedule) {
			if ($schedule->wasChanged('user_id')) {
				$schedule->schedule_users()->withTrashed()->where('user_id', $schedule->user_id)->get()->each->forceDelete();
			}
		});
		// Participants are not decided on their own: they share the schedule's status
		static::updated(fn(Schedule $schedule) => $schedule->shareStatusWithParticipants());
		// ...and approved schedules left without anyone who may go alone go back to pending
		static::updated(fn(Schedule $schedule) => \App\Services\PrimaryScheduleRule::afterScheduleChange($schedule));
		static::deleted(fn(Schedule $schedule) => \App\Services\PrimaryScheduleRule::afterScheduleChange($schedule, deleted: true));
	}

	public function schedule_type()
	{
		return $this->belongsTo(ScheduleType::class);
	}

	/** The slot (shift) it was booked on, for types that use slots. */
	public function slot()
	{
		return $this->belongsTo(ScheduleTypeSlot::class, 'schedule_type_slot_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function schedule_users()
	{
		return $this->hasMany(ScheduleUser::class); // Relates to the new pivot model
	}

	/** The people who cancelled their participation (or were taken off): shown as cancelled. */
	public function cancelled_schedule_users()
	{
		return $this->hasMany(ScheduleUser::class)->onlyTrashed();
	}

	/** Approve the schedule and any participant still waiting. A message is appended to the internal note. */
	public function approve(?string $message = null): void
	{
		$this->update(['status' => ScheduleStatus::Approved] + $this->noteWith($message, __('Approved')));
	}

	/** Reject the schedule and every participant. A message is appended to the internal note. */
	public function reject(?string $message = null): void
	{
		$this->update(['status' => ScheduleStatus::Rejected] + $this->noteWith($message, __('Rejected')));
	}

	/**
	 * Participants have the schedule's status (pending, approved, rejected, cancelled), whatever
	 * changed it: a decision, an edit, the primary rule putting it back to pending. What happened
	 * to each person once it took place (completed, not shown) is theirs and is kept.
	 */
	public function shareStatusWithParticipants(): void
	{
		if (! $this->wasChanged('status') || ! $this->status || $this->status->isOutcome()) {
			return;
		}

		$this->schedule_users()
			->whereNotIn('status', [ScheduleStatus::Completed, ScheduleStatus::NotShown, $this->status])
			->update(['status' => $this->status]);
	}

	/** The status a person added to this schedule gets: the schedule's, unless it already took place. */
	public static function statusForNewParticipant(int $scheduleId): ?ScheduleStatus
	{
		$status = static::withTrashed()->whereKey($scheduleId)->first(['id', 'status'])?->status;

		return $status && ! $status->isOutcome() ? $status : null;
	}

	/** Its owner did not come (participants keep their own status). A message is appended to the internal note. */
	public function markNotShown(?string $message = null): void
	{
		$this->update(['status' => ScheduleStatus::NotShown] + $this->noteWith($message, __('Not shown')));
	}

	/**
	 * The note with a decision message appended below what is already there:
	 * "[2026-10-16 14:30] Maria · Rejected: text". Nothing changes when the message is empty.
	 *
	 * @return array{internal_note?: string}
	 */
	private function noteWith(?string $message, string $decision): array
	{
		$message = trim((string) $message);

		if ($message === '') {
			return [];
		}

		$entry = sprintf(
			'[%s] %s · %s: %s',
			now()->format(config('app.date_time_format')),
			auth()->user()?->name ?? __('System'),
			$decision,
			$message
		);

		return ['internal_note' => filled($this->internal_note) ? $this->internal_note . "\n" . $entry : $entry];
	}

	public static function getAllowedFields(): array
	{
		return [
			'id',
			'start',
			'end',
			'all_day',
			'description',
			'internal_note',
			'status',
			'user_id',
			'schedule_type_id',
			'schedule_type_slot_id',
		];
	}

	public static function getAllowedSorts(): array
	{
		return [
			'id',
			'start',
			'status',
		];
	}

	public static function getAllowedFilters(): array
	{
		return [
			'status',
			'all_day', // Assuming this is a boolean or similar
			'start',   // Allow filtering by start date
			'end',     // Allow filtering by end date
			'user_id', // Allow filtering by user ID
			'schedule_type_id', // Allow filtering by schedule type ID
			'status'
		];
	}
#API
	public static function getValidationRules(): array
    {
        return [
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
            'all_day' => 'sometimes|boolean',
            'description' => 'nullable|string|max:255',
            'internal_note' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::enum(ScheduleStatus::class)],
            'user_id' => 'nullable|exists:users,id',
            'schedule_type_id' => 'required|exists:schedule_types,id',
        ];
    }
}
