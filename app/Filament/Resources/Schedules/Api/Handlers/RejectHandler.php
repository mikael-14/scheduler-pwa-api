<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;

/**
 * Reject Schedule: same rules as approving, with the opposite outcome.
 */
class RejectHandler extends ApproveHandler
{
    public static string | null $uri = '/{id}/reject';

    protected ScheduleStatus $target = ScheduleStatus::Rejected;
}
