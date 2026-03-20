<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Schedule
 * 
 * @property int $id
 * @property Carbon $date
 * @property Carbon $time
 * @property string|null $description
 * @property string|null $internal_note
 * @property string $status
 * @property int|null $user_id
 * @property int|null $schedule_type_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property ScheduleType|null $schedule_type
 * @property User|null $user
 *
 * @package App\Models
 */
class Schedule extends Model
{
	use SoftDeletes;
	protected $table = 'schedules';

	protected $casts = [
		'date' => 'datetime',
		'time' => 'datetime',
		'user_id' => 'int',
		'schedule_type_id' => 'int'
	];

	protected $fillable = [
		'date',
		'time',
		'description',
		'internal_note',
		'status',
		'user_id',
		'schedule_type_id'
	];

	public function schedule_type()
	{
		return $this->belongsTo(ScheduleType::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
