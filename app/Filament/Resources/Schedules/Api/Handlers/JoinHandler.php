<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use App\Services\ScheduleParticipation;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class JoinHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}/participation';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'participate_schedule';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Join a schedule
     *
     * Adds the signed-in user as a participant of someone else's pending or approved schedule,
     * from today on. The participation starts pending; approvers are told.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $actor = $request->user();
        $schedule = ScheduleAccess::scopeVisible(static::getModel()::query(), $actor)
            ->with('schedule_users')
            ->find($request->route('id'));

        if (! $schedule) {
            return static::sendNotFoundResponse();
        }

        if (! ScheduleAccess::canJoin($actor, $schedule)) {
            return response()->json([
                'message' => __('You cannot join this schedule.'),
                'code' => 'cannot_join',
            ], 422);
        }

        ScheduleParticipation::join($actor, $schedule);

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh(ScheduleTransformer::RELATIONS)),
            'Joined'
        );
    }
}
