<?php

namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaginationHandler extends ApprovedHandler
{
    public static string | null $uri = '/';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'view_any_schedule';

    private const MAX_PER_PAGE = 500;

    /**
     * Define allowed filters for Spatie QueryBuilder
     */
    public function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('user_id'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('schedule_type_id'),

            // "My agenda": schedules I own or take part in
            AllowedFilter::callback('mine', function ($query, $value) {
                if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }
                $userId = request()->user()->id;
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhereHas('schedule_users', fn($su) => $su->where('user_id', $userId));
                });
            }),

            // Window filters match anything that overlaps the window, so multi-day
            // schedules that started before it are still returned
            AllowedFilter::callback('start_from', function ($query, $value) {
                $from = \Carbon\Carbon::parse($value)->startOfDay();
                $query->whereRaw('COALESCE(`end`, `start`) >= ?', [$from]);
            }),
            AllowedFilter::callback('start_to', function ($query, $value) {
                $to = \Carbon\Carbon::parse($value)->endOfDay();
                $query->where('start', '<=', $to);
            }),
        ];
    }

    /**
     * List of Schedule
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    #[QueryParameter('filter[start_from]', description: 'Schedules ending on/after this date', type: 'string', example: '2026-10-01')]
    #[QueryParameter('filter[start_to]', description: 'Schedules starting on/before this date', type: 'string', example: '2026-10-31')]
    #[QueryParameter('filter[mine]', description: 'Only schedules I own or take part in', type: 'boolean', example: 'true')]
    #[QueryParameter('filter[status]', description: 'Exact status', type: 'string', example: 'approved')]
    #[QueryParameter('filter[user_id]', description: 'Owner user id', type: 'integer', example: '1')]
    #[QueryParameter('filter[schedule_type_id]', description: 'Schedule type id', type: 'integer', example: '1')]
    public function handler()
    {
        $perPage = min(max((int) request()->query('per_page', 25), 1), self::MAX_PER_PAGE);

        $query = static::getEloquentQuery()->with(ScheduleTransformer::RELATIONS);

        $query = QueryBuilder::for($query)
            ->allowedFields($this->getAllowedFields() ?? [])
            ->allowedSorts($this->getAllowedSorts() ?? [])
            ->allowedFilters($this->getAllowedFilters() ?? [])
            ->allowedIncludes($this->getAllowedIncludes() ?? [])
            ->paginate($perPage)
            ->appends(request()->query());

        return ScheduleTransformer::collection($query);
    }
}
