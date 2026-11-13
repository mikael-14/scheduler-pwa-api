<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * The tags of an audit entry (audits.tags, shown on the audit's page): where the change came
 * from, what kind of record it is, and what kind of change it was. Each auditable model lists
 * its own in generateTags() through make().
 *
 * Tags are stored as written (short English keys, plus names such as the schedule type's), so
 * they read the same whatever language the panel is in, and can be searched.
 */
class AuditTags
{
    /**
     * @param  list<string|null|false>  $tags  null / false / '' are left out
     * @return list<string>
     */
    public static function make(Model $model, array $tags): array
    {
        return collect([self::source(), ...$tags])
            ->filter(fn($tag) => is_string($tag) && trim($tag) !== '')
            // the tags column is comma separated: a comma inside a name would split it
            ->map(fn(string $tag) => trim(str_replace(',', ' ', $tag)))
            ->unique()
            ->values()
            ->all();
    }

    /** "pwa" (the API), "admin" (the panel) or "system" (scheduled commands, artisan). */
    public static function source(): string
    {
        if (request()->is('api/*')) {
            return 'pwa';
        }

        return app()->runningInConsole() && ! app()->runningUnitTests() ? 'system' : 'admin';
    }

    /** The audit being written is an update that changed one of these columns. */
    public static function changed(Model $model, string ...$columns): bool
    {
        return $model->getAuditEvent() === 'updated' && $model->wasChanged($columns);
    }
}
