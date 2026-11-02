<?php
namespace App\Filament\Resources\ScheduleTypes\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ScheduleType;

/**
 * A schedule type as the PWA's management screens use it.
 *
 * @property ScheduleType $resource
 */
class ScheduleTypeTransformer extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $type = $this->resource;
        $user = $request->user();
        // loaded by the list (withCount); counted here for a single type
        $schedules = $type->schedules_count ?? $type->schedules()->count();

        return [
            'id' => $type->id,
            'name' => $type->name,
            'color' => $type->color,
            'description' => $type->description,
            'status' => (bool) $type->status,
            // set in the database only: shown, never edited from the API
            'primary' => (bool) $type->primary,
            'range' => (bool) $type->range,
            'all_day' => (bool) $type->all_day,
            // date window (ISO, the PWA shows it in the server's timezone)
            'start' => $type->start?->toIso8601String(),
            'end' => $type->end?->toIso8601String(),
            // daily hours, "HH:mm" (time columns: the raw value, not the datetime cast)
            'min_time' => self::hhmm($type->getRawOriginal('min_time')),
            'max_time' => self::hhmm($type->getRawOriginal('max_time')),
            // active schedules (deleted ones don't count, see ScheduleType::canBeDeleted)
            'schedules_count' => (int) $schedules,
            'can' => [
                'update' => (bool) $user?->can('update_schedule_type'),
                'delete' => (bool) $user?->can('delete_schedule_type') && $schedules === 0,
            ],
            'created_at' => $type->created_at?->toIso8601String(),
            'updated_at' => $type->updated_at?->toIso8601String(),
        ];
    }

    /** "09:00:00", or "2026-10-31 09:00:00" right after a save (the datetime cast) → "09:00" */
    private static function hhmm(?string $time): ?string
    {
        return $time && preg_match('/(\d{2}:\d{2})(?::\d{2})?$/', $time, $m) ? $m[1] : null;
    }
}
