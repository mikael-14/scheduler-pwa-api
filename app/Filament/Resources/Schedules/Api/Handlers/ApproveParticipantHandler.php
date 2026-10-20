<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use App\Services\ScheduleParticipation;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class ApproveParticipantHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}/participants/{userId}/approve';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'approve_schedule|update_status_schedule';

    /** Overridden by RejectParticipantHandler. */
    protected ScheduleStatus $target = ScheduleStatus::Approved;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Approve a participation
     *
     * Decides on one pending participant of a schedule (someone who joined it). On the primary
     * type, a person who may not go alone is only approved when someone who may is going at
     * overlapping hours (422 otherwise). Anyone with `update_status_schedule` may also change a
     * participation that is no longer pending.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        $schedule = static::getModel()::find($request->route('id'));
        $participant = $schedule?->schedule_users()->where('user_id', (int) $request->route('userId'))->first();

        if (! $participant) {
            return static::sendNotFoundResponse();
        }

        if ($participant->status !== ScheduleStatus::Pending && ! ScheduleAccess::canSetAnyStatus($request->user())) {
            return response()->json([
                'message' => __('Only pending participations can be approved or rejected.'),
                'code' => 'not_pending',
            ], 422);
        }

        ScheduleParticipation::decide($participant, $this->target, $data['message'] ?? null);

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh(ScheduleTransformer::RELATIONS)),
            $this->target === ScheduleStatus::Approved ? 'Participation approved' : 'Participation rejected'
        );
    }
}
