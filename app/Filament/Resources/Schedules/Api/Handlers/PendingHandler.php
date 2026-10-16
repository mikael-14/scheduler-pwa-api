<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use Illuminate\Http\Request;

class PendingHandler extends ApprovedHandler
{
    public static string | null $uri = '/pending';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'approve_schedule|update_status_schedule';

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Schedules waiting for a decision
     *
     * The approver's work list, oldest start first. Available to anyone who can approve, so an
     * approve-only role does not need `view_any_schedule` to see what it has to decide.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $schedules = static::getModel()::query()
            ->where('status', ScheduleStatus::Pending)
            ->with(['user:id,name', 'schedule_type:id,name,color', 'schedule_users.user:id,name'])
            ->orderBy('start')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $schedules->map(fn($schedule) => [
                'id' => $schedule->id,
                'start' => $schedule->start,
                'end' => $schedule->end,
                'all_day' => $schedule->all_day,
                'description' => $schedule->description,
                'status' => $schedule->status,
                'user' => $schedule->user ? ['id' => $schedule->user->id, 'name' => $schedule->user->name] : null,
                'schedule_type' => $schedule->schedule_type
                    ? $schedule->schedule_type->only(['id', 'name', 'color'])
                    : null,
                'participants' => $schedule->schedule_users
                    ->map(fn($su) => $su->user?->name)
                    ->filter()
                    ->values(),
            ])->values(),
        ]);
    }
}
