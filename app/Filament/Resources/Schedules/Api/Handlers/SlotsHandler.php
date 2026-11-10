<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleSlots;
use Illuminate\Http\Request;

class SlotsHandler extends ApprovedHandler
{
    public static string | null $uri = '/slots';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'create_schedule|participate_schedule';

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Slots of a schedule type
     *
     * For types that use slots (shifts, `uses_slots`): the slots that have not ended, earliest
     * first, each with its comment, how many people it has (`people`) and takes (`capacity`,
     * null = no limit), whether it is `full`, the signed-in user is on it (`mine`), its schedule
     * (`schedule_id`, null until someone takes it) and why it can't be taken (`problem`, or null).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $data = $request->validate(['schedule_type_id' => ['required', 'integer', 'exists:schedule_types,id']]);

        return response()->json(['data' => ScheduleSlots::listFor($request->user(), (int) $data['schedule_type_id'])]);
    }
}
