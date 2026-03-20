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

	protected $fillable = [
		'name',
		'color'
	];

	public function schedules()
	{
		return $this->hasMany(Schedule::class);
	}
}
