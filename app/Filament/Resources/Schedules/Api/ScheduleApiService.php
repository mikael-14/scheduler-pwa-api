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
            Handlers\PatchHandler::class,
            Handlers\ApproveHandler::class,
            Handlers\RejectHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            // before DetailHandler, whose `/{id}` would otherwise swallow `/pending`
            Handlers\PendingHandler::class,
            Handlers\FormOptionsHandler::class,
            Handlers\CalendarOptionsHandler::class,
            Handlers\DetailHandler::class
        ];

    }
}
