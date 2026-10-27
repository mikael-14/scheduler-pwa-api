<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class ParticipantNotShownHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}/participants/{userId}/not-shown';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'update_schedule|update_status_schedule';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Mark a participant as not shown
     *
     * One of the schedule's approved participants did not come. The schedule's owner (or anyone
     * with `update_status_schedule`) marks it, once the schedule has started.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $schedule = static::getModel()::find($request->route('id'));
        $participant = $schedule?->schedule_users()->where('user_id', (int) $request->route('userId'))->first();

        if (! $participant) {
            return static::sendNotFoundResponse();
        }

        if (! ScheduleAccess::canMarkParticipantNotShown($request->user(), $schedule, $participant)) {
            return response()->json([
                'message' => __('Only the owner can mark an approved participant as not shown, once the schedule has started.'),
                'code' => 'cannot_mark_not_shown',
            ], 422);
        }

        $participant->update(['status' => ScheduleStatus::NotShown]);

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh(ScheduleTransformer::RELATIONS)),
            'Participant marked as not shown'
        );
    }
}
