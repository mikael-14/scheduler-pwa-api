<?php
namespace App\Filament\Resources\Schedules\Api\Transformers;

use App\Models\Schedule;
use App\Services\ScheduleAccess;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Schedule $resource
 */
class ScheduleTransformer extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data = $this->resource->toArray();

        // The internal note is for staff only, never for the people on the shared agenda
        $user = $request->user();
        if (! $user || ! ScheduleAccess::isStaff($user)) {
            unset($data['internal_note']);
        }

        $data['schedule_users'] = $this->resource->schedule_users->map(function ($scheduleUser) {
            return [
                'id' => $scheduleUser->id,
                'user_id' => $scheduleUser->user_id,
                'status' => $scheduleUser->status,
                'description' => $scheduleUser->description,
            ];
        });
        return $data;
    }

}
