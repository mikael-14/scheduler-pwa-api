<?php

namespace App\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/**
 * Puts role and permission changes in the audits (Spatie does not fire model events for them):
 *
 * - a user's roles ("roles": before → after), e.g. given on approval or in the user form. Changing
 *   them (syncRoles: all removed, then the new ones added) is one entry, not two;
 * - the permissions of a role, or given straight to a user ("permissions": before → after).
 *   Spatie removes the old ones without an event when they are replaced (syncPermissions, used by
 *   the Roles screen), so "before" is the list last recorded here (empty the first time).
 *
 * Who did it is the signed-in person, as in every audit. Needs permission.events_enabled.
 */
class RecordRoleChanges
{
    /** A roles entry just written on a removal, to be completed by the adding that follows (syncRoles). */
    private static array $open = [];

    public function handleRoleDetached(RoleDetached $event): void
    {
        $model = $event->model;
        if (! $model instanceof Auditable || ! method_exists($model, 'roles')) {
            return;
        }

        $now = $this->roleNames($model);
        $before = $now->merge($this->namesOf($event->rolesOrIds, config('permission.models.role')))->unique()->sort()->values();

        if ($audit = $this->write($model, 'roles', $before, $now)) {
            self::$open[$this->key($model)] = $audit->getKey();
        }
    }

    public function handleRoleAttached(RoleAttached $event): void
    {
        $model = $event->model;
        if (! $model instanceof Auditable || ! method_exists($model, 'roles')) {
            return;
        }

        $now = $this->roleNames($model);
        $key = $this->key($model);

        // the second half of a change: the removal's entry gets the final list
        if (isset(self::$open[$key])) {
            $audit = Audit::find(self::$open[$key]);
            unset(self::$open[$key]);

            if ($audit && $audit->created_at?->gt(now()->subMinute())) {
                if (($audit->old_values['roles'] ?? '') === $this->text($now)) {
                    $audit->delete(); // the same roles again: nothing changed

                    return;
                }
                $audit->update(['new_values' => ['roles' => $this->text($now)]]);

                return;
            }
        }

        $added = $this->namesOf($event->rolesOrIds, config('permission.models.role'));
        $this->write($model, 'roles', $now->diff($added)->values(), $now);
    }

    public function handlePermissionAttached(PermissionAttached $event): void
    {
        $this->permissions($event->model);
    }

    public function handlePermissionDetached(PermissionDetached $event): void
    {
        $this->permissions($event->model);
    }

    private function permissions(Model $model): void
    {
        if (! $model instanceof Auditable || ! method_exists($model, 'permissions')) {
            return;
        }

        $now = $model->permissions()->pluck('name')->sort()->values();
        $last = Audit::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->where('event', 'permissions')
            ->latest('id')
            ->value('new_values');
        $before = collect(array_filter(explode(', ', (string) ($last['permissions'] ?? ''))));

        $this->write($model, 'permissions', $before, $now);
    }

    /** One audit entry, "field: before → after"; nothing when they are the same. */
    private function write(Model $model, string $field, Collection $before, Collection $after): ?Audit
    {
        if ($this->text($before) === $this->text($after)) {
            return null;
        }

        $model->auditEvent = $field;
        $model->isCustomEvent = true;
        $model->auditCustomOld = [$field => $this->text($before)];
        $model->auditCustomNew = [$field => $this->text($after)];

        try {
            Event::dispatch(new AuditCustom($model));
        } finally {
            $model->auditCustomOld = $model->auditCustomNew = null;
            $model->isCustomEvent = false;
            $model->auditEvent = null;
        }

        return Audit::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->where('event', $field)
            ->latest('id')
            ->first();
    }

    private function roleNames(Model $model): Collection
    {
        return $model->roles()->pluck('name')->sort()->values();
    }

    /** Names of the roles / permissions in an event (ids, models or names). */
    private function namesOf(mixed $items, string $class): Collection
    {
        $items = collect($items instanceof Model ? [$items] : $items)->flatten();
        $ids = $items->map(fn($item) => $item instanceof Model ? $item->getKey() : $item);

        return $class::query()
            ->whereIn('id', $ids->filter(fn($id) => is_numeric($id))->all())
            ->orWhereIn('name', $ids->reject(fn($id) => is_numeric($id))->all())
            ->pluck('name');
    }

    private function text(Collection $names): string
    {
        return $names->filter()->unique()->sort()->implode(', ');
    }

    /** The same model object: syncRoles removes and adds on the same instance. */
    private function key(Model $model): string
    {
        return $model::class . '#' . $model->getKey() . '#' . spl_object_id($model);
    }
}
