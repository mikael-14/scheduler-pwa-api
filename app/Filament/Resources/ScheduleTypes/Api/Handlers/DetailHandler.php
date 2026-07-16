<?php

namespace App\Filament\Resources\ScheduleTypes\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ScheduleTypes\Api\Transformers\ScheduleTypeTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleTypeResource::class;
    protected static string $permission = 'view_schedule_type';


    /**
     * Show ScheduleType
     *
     * @param Request $request
     * @return ScheduleTypeTransformer
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');
        
        $query = static::getEloquentQuery();

        $query = QueryBuilder::for(
            $query->where(static::getKeyName(), $id)
        )
            ->first();

        if (!$query) return static::sendNotFoundResponse();

        return new ScheduleTypeTransformer($query);
    }
}
