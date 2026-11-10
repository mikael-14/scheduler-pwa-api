<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Models\ScheduleTypeSlot;
use App\Services\ScheduleSlots;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

class TakeSlotHandler extends ApprovedHandler
{
    public static string | null $uri = '/slots/{slot}';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'create_schedule|participate_schedule';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Take a slot
     *
     * A place on a slot (shift): the first person creates its schedule (pending, as usual), the
     * next ones join it. A full slot, one that started, or one the user is already on gives 422
     * with the reason in `errors.slot`. Returns the schedule.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $slot = ScheduleTypeSlot::find($request->route('slot'));

        if (! $slot) {
            return static::sendNotFoundResponse();
        }

        $schedule = ScheduleSlots::take($request->user(), $slot);

        return static::sendSuccessResponse(
            new ScheduleTransformer($schedule->fresh(ScheduleTransformer::RELATIONS)),
            'Slot taken'
        );
    }
}
