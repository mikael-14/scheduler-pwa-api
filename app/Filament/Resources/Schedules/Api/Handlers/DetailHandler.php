<?php

namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\ScheduleResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;

class DetailHandler extends \App\Http\Handlers\ApprovedHandler
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'view_schedule';


    /**
     * Show Schedule
     *
     * @param Request $request
     * @return ScheduleTransformer
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');
        
        $model = QueryBuilder::for(static::getEloquentQuery())
            ->where(static::getKeyName(), $id)
            ->allowedFields($this->getAllowedFields() ?? [])
            ->allowedIncludes($this->getAllowedIncludes() ?? [])
            ->first();

        if (!$model) return static::sendNotFoundResponse();

        $model->load(ScheduleTransformer::RELATIONS);

        return new ScheduleTransformer($model);
    }
}
