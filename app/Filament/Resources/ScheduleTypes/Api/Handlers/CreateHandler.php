<?php
namespace App\Filament\Resources\ScheduleTypes\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use App\Filament\Resources\ScheduleTypes\Api\Requests\CreateScheduleTypeRequest;
use App\Filament\Resources\ScheduleTypes\Api\Transformers\ScheduleTypeTransformer;

class CreateHandler extends \App\Http\Handlers\ApprovedHandler
{
    public static string | null $uri = '/';
    public static string | null $resource = ScheduleTypeResource::class;
    protected static string $permission = 'create_schedule_type';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create ScheduleType
     *
     * @param CreateScheduleTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateScheduleTypeRequest $request)
    {
        $model = new (static::getModel());

        // only the validated fields: anything else (e.g. 'primary') is ignored
        $model->fill($request->validated());

        $model->save();

        return static::sendSuccessResponse(new ScheduleTypeTransformer($model), "Successfully Create Resource");
    }
}