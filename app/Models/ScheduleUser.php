<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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
class ScheduleUser extends Model
{
	protected $table = 'schedule_users';

	protected $casts = [
		'schedule_id' => 'int',
		'user_id' => 'int'
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

	
}
