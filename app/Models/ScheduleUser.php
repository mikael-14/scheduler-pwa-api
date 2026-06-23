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
