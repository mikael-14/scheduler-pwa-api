<?php

namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\ScheduleResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'View:Schedule';


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

        $model->load('schedule_users');

        return new ScheduleTransformer($model);
    }
}
