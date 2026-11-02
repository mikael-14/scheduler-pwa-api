<?php
namespace App\Filament\Resources\ScheduleTypes\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;

class DeleteHandler extends \App\Http\Handlers\ApprovedHandler
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleTypeResource::class;
    protected static string $permission = 'delete_schedule_type';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Delete ScheduleType
     *
     * Refused while the type has schedules (same rule as the admin panel). The audit log
     * keeps the deleted type's values.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        if (! $model->canBeDeleted()) {
            return response()->json([
                'message' => __('This record has related schedules and cannot be deleted.'),
                'code' => 'has_schedules',
            ], 422);
        }

        $model->delete();

        return static::sendSuccessResponse($model, "Successfully Delete Resource");
    }
}
