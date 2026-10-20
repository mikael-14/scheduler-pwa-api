<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\ScheduleTypeRules;
use Illuminate\Http\Request;

class CalendarOptionsHandler extends ApprovedHandler
{
    public static string | null $uri = '/calendar-options';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'view_any_schedule';

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * What the agenda calendar shows
     *
     * The hours to show ("HH:mm:ss", null = no limit): from the earliest min_time to the latest
     * max_time of the active types, so no type is cut off. Display only, like the admin panel's
     * calendar: schedules outside these hours are still allowed.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $types = ScheduleTypeRules::activeTypes();

        return response()->json([
            'data' => [
                'hours' => ScheduleTypeRules::calendarHoursFor($types),
                'timezone' => config('app.timezone'),
            ],
        ]);
    }
}
