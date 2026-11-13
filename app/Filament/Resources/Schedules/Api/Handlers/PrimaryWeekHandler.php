<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Services\AgendaSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class PrimaryWeekHandler extends ApprovedHandler
{
    public static string | null $uri = '/primary-week';
    public static string | null $resource = ScheduleResource::class;

    /** Every signed-in, active, approved account: only counts are sent, never who. */
    public static function getRouteMiddleware(): array
    {
        return ['auth:sanctum', 'active', 'approved'];
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * The primary calendar, today and the next 6 days
     *
     * For the PWA's home strip: per day, how many people are approved (`approved`) or only
     * requested (`pending`), how many schedules were `cancelled` or rejected, and the `state`
     * ('approved', 'pending', 'cancelled' or 'empty'). `type` is null when no type is primary.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $type = AgendaSummary::primaryType();

        return response()->json(['data' => [
            'type' => $type ? ['id' => $type->id, 'name' => $type->name, 'color' => $type->color] : null,
            'days' => $type ? AgendaSummary::coverage(CarbonImmutable::today(), 7, $type->id) : [],
        ]]);
    }
}
