<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Class ModelHasRole
 * 
 * @property int $role_id
 * @property string $model_type
 * @property int $model_id
 * 
 * @property Role $role
 *
 * @package App\Models
 */
class ModelHasRole extends Model implements Auditable
{
	use \OwenIt\Auditing\Auditable;

	protected $table = 'model_has_roles';
	
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'role_id' => 'int',
		'model_id' => 'int'
	];

	public function role()
	{
		return $this->belongsTo(Role::class);
	}
	public function user()
	{
		return $this->belongsTo(User::class,'id','model_id');
	}
	
}