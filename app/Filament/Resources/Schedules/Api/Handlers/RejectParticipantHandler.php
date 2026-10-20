<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;

/**
 * Reject a participation: same rules as approving it, with the opposite outcome.
 */
class RejectParticipantHandler extends ApproveParticipantHandler
{
    public static string | null $uri = '/{id}/participants/{userId}/reject';

    protected ScheduleStatus $target = ScheduleStatus::Rejected;
}
