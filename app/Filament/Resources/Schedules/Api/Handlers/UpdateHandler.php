<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Schedules\Api\Requests\UpdateScheduleRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'Update:Schedule';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Schedule
     *
     * @param UpdateScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateScheduleRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}