<?php

namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Http\Handlers\Concerns\WritesSchedules;
use App\Models\Schedule;
use App\Services\ScheduleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends ApprovedHandler
{
    use WritesSchedules;

    public static string | null $uri = '/';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'create_schedule';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create Schedule
     *
     * The owner is the authenticated user and the status starts as `pending`,
     * unless the caller has the permissions to book for others / set a status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $data = $request->validate($this->scheduleRules());
        $actor = $request->user();

        return DB::transaction(function () use ($data, $actor) {
            $schedule = new Schedule();
            $schedule->fill(Arr::only($data, ['start', 'end', 'all_day', 'description', 'schedule_type_id']));
            $schedule->user_id = ScheduleAccess::ownerForCreate($actor, $data['user_id'] ?? null);
            $schedule->status = ScheduleAccess::resolveStatus($actor, $data['status'] ?? null);

            if (ScheduleAccess::isStaff($actor) && array_key_exists('internal_note', $data)) {
                $schedule->internal_note = $data['internal_note'];
            }

            $schedule->save();

            if (! empty($data['schedule_users'])) {
                $this->syncParticipants($schedule, $data['schedule_users'], $actor, false);
            }

            return static::sendSuccessResponse(
                new ScheduleTransformer($schedule->load('schedule_users')),
                'Successfully Create Resource'
            );
        });
    }
}
