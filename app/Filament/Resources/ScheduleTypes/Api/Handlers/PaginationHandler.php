<?php
namespace App\Filament\Resources\ScheduleTypes\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use App\Filament\Resources\ScheduleTypes\Api\Transformers\ScheduleTypeTransformer;

class PaginationHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = ScheduleTypeResource::class;
    protected static string $permission = 'view_any_schedule_type';


    /**
     * List of ScheduleType
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function handler()
    {
        $query = static::getEloquentQuery();

        $query = QueryBuilder::for($query)
        ->allowedFields($this->getAllowedFields() ?? [])
        ->allowedSorts($this->getAllowedSorts() ?? [])
        ->allowedFilters($this->getAllowedFilters() ?? [])
        ->allowedIncludes($this->getAllowedIncludes() ?? [])
        ->paginate(request()->query('per_page'))
        ->appends(request()->query());

        return ScheduleTypeTransformer::collection($query);
    }
}
