<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Enums\ScheduleStatus;
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

	public function schedule_users()
	{
		return $this->hasMany(ScheduleUser::class); // Relates to the new pivot model
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
            'end' => 'nullable|date',
            'all_day' => 'required|boolean',
            'description' => 'nullable|string',
            'internal_note' => 'nullable|string',
            'status' => ['required', Rule::enum(ScheduleStatus::class)],
            'user_id' => 'nullable|exists:users,id',
            'schedule_type_id' => 'nullable|exists:schedule_types,id',
        ];
    }
}
