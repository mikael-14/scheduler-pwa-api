<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleAccess;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class DeleteHandler extends ApprovedHandler
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'delete_schedule';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Delete Schedule
     *
     * Owners can delete their own schedules only while they are pending or cancelled
     * (approved ones are cancelled instead, so the agenda keeps its history).
     * Deleting anyone's schedule needs `delete_any_schedule`.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $schedule = static::getModel()::find($request->route('id'));

        if (! $schedule) {
            return static::sendNotFoundResponse();
        }

        $actor = $request->user();

        if (! ScheduleAccess::canDelete($actor, $schedule)) {
            return response()->json([
                'message' => __('You can only delete your own schedules.'),
                'code' => 'forbidden',
            ], 403);
        }

        $ownerOnly = ! $actor->can('delete_any_schedule');
        $deletable = in_array($schedule->status, [ScheduleStatus::Pending, ScheduleStatus::Cancelled], true);

        if ($ownerOnly && ! $deletable) {
            return response()->json([
                'message' => __('Cancel this schedule instead of deleting it.'),
                'code' => 'cancel_instead',
            ], 422);
        }

        $schedule->delete();

        return static::sendSuccessResponse(null, 'Successfully Delete Resource');
    }
}
