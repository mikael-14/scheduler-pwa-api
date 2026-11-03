<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The roles (config/permission.php models.role), audited: renaming a role is in its history, and
 * so are the permissions it is given or loses (App\Listeners\RecordRoleChanges).
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Role extends SpatieRole implements Auditable
{
	use \OwenIt\Auditing\Auditable;

	public function model_has_roles()
	{
		return $this->hasMany(ModelHasRole::class);
	}
}
