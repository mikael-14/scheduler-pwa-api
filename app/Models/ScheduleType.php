<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Services\AuditTags;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
/**
 * Class ScheduleType
 * 
 * @property int $id
 * @property string $name
 * @property string|null $color
 * @property bool $status
 * @property bool $range
 * @property bool $all_day
 * @property bool $uses_slots  people pick one of its slots (shifts) instead of their own hours
 * @property Carbon|null $start
 * @property Carbon|null $end
 * @property string|null $description
 * @property Carbon|null $min_time
 * @property Carbon|null $max_time
 * @property bool $primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|Schedule[] $schedules
 *
 * @package App\Models
 */
class ScheduleType extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

	protected $table = 'schedule_types';

	/** A changed type must not keep its old rules for the rest of the request (ScheduleTypeRules). */
	protected static function booted(): void
	{
		static::saved(fn() => \App\Services\ScheduleTypeRules::flush());
		static::deleted(fn() => \App\Services\ScheduleTypeRules::flush());
	}

	protected $casts = [
		'status' => 'bool',
		'range' => 'bool',
		'all_day' => 'bool',
		'uses_slots' => 'bool',
		'start' => 'datetime',
		'end' => 'datetime',
		'min_time' => 'datetime',
		'max_time' => 'datetime',
		'primary' => 'bool'
	];

	protected $fillable = [
		'name',
		'color',
		'status',
		'range',
		'all_day',
		'uses_slots',
		'start',
		'end',
		'description',
		'min_time',
		'max_time',
		'primary'
	];

	public function schedules()
	{
		return $this->hasMany(Schedule::class);
	}

	/** Its fixed times, when it uses slots (shifts). */
	public function slots()
	{
		return $this->hasMany(ScheduleTypeSlot::class)->orderBy('start');
	}

	/**
	 * A type with schedules can't be deleted (admin panel and API). Only active schedules count:
	 * deleted ones (soft deleted) don't block, and lose their type (the foreign key nulls it).
	 */
	public function canBeDeleted(): bool
	{
		return ! $this->schedules()->exists();
	}

	/** The audit's tags: the type's name and what kind of change. See AuditTags. */
	public function generateTags(): array
	{
		return AuditTags::make($this, [
			'schedule-type',
			$this->name,
			$this->uses_slots ? 'slots' : null,
			AuditTags::changed($this, 'status') ? 'activation' : null,
			AuditTags::changed($this, 'start', 'end', 'min_time', 'max_time') ? 'dates-and-hours' : null,
			AuditTags::changed($this, 'range', 'all_day', 'uses_slots', 'primary') ? 'rules' : null,
		]);
	}
}
