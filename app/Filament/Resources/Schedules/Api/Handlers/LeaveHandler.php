<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use App\Services\ScheduleParticipation;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class LeaveHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}/participation';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'cancel_participation_schedule';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Leave a schedule
     *
     * Cancels the signed-in user's participation in a schedule, from today on. The owner is told.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $actor = $request->user();
        $schedule = static::getModel()::with('schedule_users')->find($request->route('id'));

        if (! $schedule) {
            return static::sendNotFoundResponse();
        }

        if (! ScheduleAccess::canLeave($actor, $schedule)) {
            return response()->json([
                'message' => __('You cannot leave this schedule.'),
                'code' => 'cannot_leave',
            ], 422);
        }

        ScheduleParticipation::leave($actor, $schedule);

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh(ScheduleTransformer::RELATIONS)),
            'Left'
        );
    }
}
