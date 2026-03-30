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
 * @property string $App\Enums\ScheduleType
 * @property Carbon|null $start
 * @property Carbon|null $end
 * @property string|null $description
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
		'start' => 'datetime',
		'end' => 'datetime'
	];

	protected $fillable = [
		'name',
		'color',
		'status',
		'type',
		'start',
		'end',
		'description'
	];

	public function schedules()
	{
		return $this->hasMany(Schedule::class);
	}
}
