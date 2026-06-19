<?php
namespace App\Filament\Resources\Schedules\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Schedule;

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
