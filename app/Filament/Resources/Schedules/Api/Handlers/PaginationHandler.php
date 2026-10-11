<?php

namespace App\Filament\Resources\Schedules\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use Dedoc\Scramble\Attributes\QueryParameter;

class PaginationHandler extends Handlers
{
    public static string | null $uri = '/';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'view_any_schedule';
    /**
     * Define allowed filters for Spatie QueryBuilder
     */
    public function getAllowedFilters(): array
    {
        return [

            // Optional: Start date range filtering (From / Until)
            AllowedFilter::callback('start_from', function ($query, $value) {
                $value = \Carbon\Carbon::parse($value)->startOfDay();
                $query->where('start', '>=', $value);
            }),
            AllowedFilter::callback('start_to', function ($query, $value) {
                $value = \Carbon\Carbon::parse($value)->endOfDay();
                $query->where('start', '<=', $value);
            }),
        ];
    }

    /**
     * List of Schedule
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter[start_from]', description: 'Filter start date from', type: 'string', example: '2026-10-01')]
    #[QueryParameter('filter[start_to]', description: 'Filter start date to', type: 'string', example: '2026-10-31')]
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

        return ScheduleTransformer::collection($query);
    }
}
