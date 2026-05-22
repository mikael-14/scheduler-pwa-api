<?php
namespace App\Filament\Resources\Schedules\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\Schedules\ScheduleResource;


class ScheduleApiService extends ApiService
{
    protected static string | null $resource = ScheduleResource::class;

    public static function handlers() : array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class
        ];

    }
}
