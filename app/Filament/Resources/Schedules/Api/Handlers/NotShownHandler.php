<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class NotShownHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}/not-shown';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'update_status_schedule';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Mark a schedule as not shown
     *
     * Its owner did not come. Only with `update_status_schedule`, once the schedule has started,
     * on an approved (or already completed) schedule. Participants keep their own status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        // Optional reason, appended to the schedule's internal note (staff only)
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        $schedule = static::getModel()::with('schedule_users')->find($request->route('id'));

        if (! $schedule) {
            return static::sendNotFoundResponse();
        }

        if (! ScheduleAccess::canMarkNotShown($request->user(), $schedule)) {
            return response()->json([
                'message' => __('Only approved schedules that have started can be marked as not shown.'),
                'code' => 'cannot_mark_not_shown',
            ], 422);
        }

        $schedule->markNotShown($data['message'] ?? null);

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh(ScheduleTransformer::RELATIONS)),
            'Schedule marked as not shown'
        );
    }
}
