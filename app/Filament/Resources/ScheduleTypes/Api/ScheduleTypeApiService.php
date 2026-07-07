<?php
namespace App\Filament\Resources\ScheduleTypes\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;


class ScheduleTypeApiService extends ApiService
{
    protected static string | null $resource = ScheduleTypeResource::class;

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
