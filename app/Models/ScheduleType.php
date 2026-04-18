<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ScheduleType
 * 
 * @property int $id
 * @property string $name
 * @property string|null $color
 * @property bool $status
 * @property bool $range
 * @property bool $all_day
 * @property Carbon|null $start
 * @property Carbon|null $end
 * @property string|null $description
 * @property Carbon|null $min_time
 * @property Carbon|null $max_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|Schedule[] $schedules
 *
 * @package App\Models
 */
class ScheduleType extends Model
{
	protected $table = 'schedule_types';

	protected $casts = [
		'status' => 'bool',
		'range' => 'bool',
		'all_day' => 'bool',
		'start' => 'datetime',
		'end' => 'datetime',
		'min_time' => 'datetime',
		'max_time' => 'datetime'
	];

	protected $fillable = [
		'name',
		'color',
		'status',
		'range',
		'all_day',
		'start',
		'end',
		'description',
		'min_time',
		'max_time'
	];

	public function schedules()
	{
		return $this->hasMany(Schedule::class);
	}
}
