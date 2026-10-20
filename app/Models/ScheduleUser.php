<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

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

	protected $table = 'schedule_users';

	protected $casts = [
		'schedule_id' => 'int',
		'user_id' => 'int',
		'status' => ScheduleStatus::class,
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
}
