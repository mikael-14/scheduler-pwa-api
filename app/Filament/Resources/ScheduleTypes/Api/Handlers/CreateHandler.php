<?php
namespace App\Filament\Resources\ScheduleTypes\Api\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SlotDefinitions;
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
        $data = $request->validated();
        $model->fill(collect($data)->except('slots')->all());

        // the slots are checked against the type as filled, then stored with it
        $plan = array_key_exists('slots', $data) ? SlotDefinitions::check($model, $data['slots']) : null;
        DB::transaction(function () use ($model, $plan) {
            $model->save();
            if ($plan !== null) {
                SlotDefinitions::save($model, $plan);
            }
        });
        $model->load('slots');

        return static::sendSuccessResponse(new ScheduleTypeTransformer($model), "Successfully Create Resource");
    }
}