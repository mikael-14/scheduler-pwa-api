<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Services\AuditTags;
use App\Enums\ScheduleStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use OwenIt\Auditing\Contracts\Auditable;
use App\Observers\ScheduleUserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;


/**
 * Class ScheduleUser
 * 
 * @property int $id
 * @property int $schedule_id
 * @property int $user_id
 * @property string $status
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Schedule $schedule
 * @property User $user
 *
 * @package App\Models
 */
#[ObservedBy(ScheduleUserObserver::class)]
class ScheduleUser extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;
	// Cancelling is a soft delete: the person stays on the schedule as cancelled (and every query,
	// count and rule ignores them); restore() is rejoining, forceDelete() removes them for good.
	use \Illuminate\Database\Eloquent\SoftDeletes;

	public const DELETED_AT = 'cancelled_at';

	protected $table = 'schedule_users';

	protected $casts = [
		'schedule_id' => 'int',
		'user_id' => 'int',
		'status' => ScheduleStatus::class,
		'cancelled_at' => 'datetime',
	];

	protected $fillable = [
		'schedule_id',
		'user_id',
		'status',
		'description'
	];

	public function schedule()
	{
		return $this->belongsTo(Schedule::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	#API
	protected static function booted(): void
	{
		// Nobody is approved on their own: a person added gets the schedule's status
		// (joining an approved schedule keeps it approved; Schedule::shareStatusWithParticipants)
		static::saving(function (ScheduleUser $participant) {
			if (! $participant->exists && $participant->schedule_id) {
				$participant->status = Schedule::statusForNewParticipant($participant->schedule_id)
					?? $participant->status
					?? ScheduleStatus::Pending;
			}
		});
		// Completed only by schedules:complete; not shown by the owner once it started
		static::saving(fn(ScheduleUser $participant) => \App\Services\ScheduleAccess::enforceParticipantStatusChange($participant));
		// The owner of a schedule is never also one of its participants
		static::saving(function (ScheduleUser $participant) {
			$ownerId = Schedule::withTrashed()->whereKey($participant->schedule_id)->value('user_id');

			if ($ownerId !== null && (int) $ownerId === (int) $participant->user_id) {
				throw \Illuminate\Validation\ValidationException::withMessages([
					'schedule_users' => [__('The owner of a schedule cannot also be a participant.')],
				]);
			}
		});
		// Primary type: who may be approved without someone who may go alone
		static::saving(fn(ScheduleUser $participant) => \App\Services\PrimaryScheduleRule::enforceOnParticipant($participant));
		// ...and approved schedules left without anyone who may go alone go back to pending
		static::updated(fn(ScheduleUser $participant) => \App\Services\PrimaryScheduleRule::afterParticipantChange($participant));
		static::deleted(fn(ScheduleUser $participant) => \App\Services\PrimaryScheduleRule::afterParticipantChange($participant, deleted: true));

		// Cancelled (soft delete: leaving, or taken off by the owner / an admin): shown as cancelled
		static::softDeleted(function (ScheduleUser $participant) {
			static::withTrashed()->whereKey($participant->getKey())->update(['status' => ScheduleStatus::Cancelled]);
			// the original stays: the `deleted` listeners (primary rule) need what it was before
			$participant->status = ScheduleStatus::Cancelled;
		});
		// Rejoining: back with the schedule's status at that moment (the primary rule is checked
		// when that is approved, in the saving hooks above)
		static::restoring(function (ScheduleUser $participant) {
			$participant->status = Schedule::statusForNewParticipant($participant->schedule_id) ?? ScheduleStatus::Pending;
		});
		// Added again (admin form, API participant list) while a cancelled row of theirs is there:
		// that old row makes way (one row per person and schedule)
		static::creating(function (ScheduleUser $participant) {
			static::onlyTrashed()
				->where('schedule_id', $participant->schedule_id)
				->where('user_id', $participant->user_id)
				->get()
				->each->forceDelete();
		});
	}

	/** Cancelled their participation (or was taken off); see SoftDeletes / cancelled_at. */
	public function isCancelled(): bool
	{
		return $this->trashed();
	}

	public static function getValidationRules(): array
	{
		return [
			'schedule_id' => 'required|exists:schedules,id',
			'user_id' => 'required|exists:users,id',
			'status' => ['required', Rule::enum(ScheduleStatus::class)],
			'description' => 'nullable|string',
		];
	}

	/** The audit's tags: a participant, of which schedule and type, and its status. See AuditTags. */
	public function generateTags(): array
	{
		$schedule = $this->schedule()->withTrashed()->first();

		return AuditTags::make($this, [
			'participant',
			$this->schedule_id ? 'schedule:' . $this->schedule_id : null,
			$schedule?->schedule_type?->name,
			$this->status?->value,
			AuditTags::changed($this, 'status') ? 'status-change' : null,
		]);
	}
}
