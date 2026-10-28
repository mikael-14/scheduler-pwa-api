<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Models\ScheduleType;
use App\Models\User;
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
     * What the calendar shows
     *
     * The hours to show ("HH:mm:ss", null = no limit): from the earliest min_time to the latest
     * max_time of the active types, so no type is cut off. Display only, like the admin panel's
     * calendar: schedules outside these hours are still allowed.
     *
     * And the choices of the calendar's filters: every type (older schedules may be of a type
     * that is off now), the statuses and the people. Without `view_inactive_schedule` other
     * people's schedules are only seen while pending or approved, so the other statuses
     * (`everyone` false) only find one's own.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $types = ScheduleTypeRules::activeTypes();
        $seesEveryStatus = $request->user()->can('view_inactive_schedule');

        return response()->json([
            'data' => [
                'hours' => ScheduleTypeRules::calendarHoursFor($types),
                'timezone' => config('app.timezone'),
                'filters' => [
                    'types' => ScheduleType::query()->orderBy('name')->get(['id', 'name', 'color', 'status'])
                        ->map(fn(ScheduleType $type) => [
                            'id' => $type->id,
                            'name' => $type->name,
                            'color' => $type->color,
                            'active' => (bool) $type->status,
                        ])
                        ->values(),
                    'statuses' => collect(ScheduleStatus::cases())
                        ->map(fn(ScheduleStatus $status) => [
                            'value' => $status->value,
                            'label' => $status->getLabel(),
                            'everyone' => $seesEveryStatus
                                || in_array($status, [ScheduleStatus::Pending, ScheduleStatus::Approved], true),
                        ])
                        ->values(),
                    // the same people as the schedule form offers
                    'people' => User::query()
                        ->where('status', true)
                        ->whereNotNull('approved_at')
                        ->orderBy('name')
                        ->get(['id', 'name', 'avatar_url'])
                        ->map(function (User $person) {
                            $avatar = $person->getFilamentAvatarUrl();

                            return [
                                'id' => $person->id,
                                'name' => $person->name,
                                'avatar_url' => $avatar ? asset($avatar) : null,
                            ];
                        })
                        ->values(),
                ],
            ],
        ]);
    }
}
