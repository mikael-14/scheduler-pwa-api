<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class ApproveHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}/approve';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'approve_schedule|update_status_schedule';

    /** Overridden by RejectHandler. */
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
     * Approve Schedule
     *
     * Needs only `approve_schedule`: the caller can approve a pending schedule (and its pending
     * participants) without being allowed to edit it. Anyone with `update_status_schedule` may also
     * do it on a schedule that is no longer pending.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $schedule = static::getModel()::with('schedule_users')->find($request->route('id'));

        if (! $schedule) {
            return static::sendNotFoundResponse();
        }

        $actor = $request->user();

        if ($schedule->status !== ScheduleStatus::Pending && ! ScheduleAccess::canSetAnyStatus($actor)) {
            return response()->json([
                'message' => __('Only pending schedules can be approved or rejected.'),
                'code' => 'not_pending',
            ], 422);
        }

        $this->target === ScheduleStatus::Approved ? $schedule->approve() : $schedule->reject();

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh('schedule_users')),
            $this->target === ScheduleStatus::Approved ? 'Schedule approved' : 'Schedule rejected'
        );
    }
}
