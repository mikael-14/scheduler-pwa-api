<?php
namespace App\Filament\Resources\ScheduleTypes\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use App\Filament\Resources\ScheduleTypes\Api\Requests\UpdateScheduleTypeRequest;
use App\Filament\Resources\ScheduleTypes\Api\Transformers\ScheduleTypeTransformer;

class UpdateHandler extends \App\Http\Handlers\ApprovedHandler
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleTypeResource::class;
    protected static string $permission = 'update_schedule_type';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update ScheduleType
     *
     * @param UpdateScheduleTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateScheduleTypeRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        // only the validated fields: anything else (e.g. 'primary') is ignored
        $model->fill($request->validated());

        $model->save();

        return static::sendSuccessResponse(new ScheduleTypeTransformer($model), "Successfully Update Resource");
    }
}