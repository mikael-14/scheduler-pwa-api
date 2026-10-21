<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Models\ScheduleType;
use App\Models\User;
use App\Services\ScheduleAccess;
use App\Services\ScheduleTypeRules;
use Illuminate\Http\Request;

class FormOptionsHandler extends ApprovedHandler
{
    public static string | null $uri = '/form-options';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'create_schedule|update_schedule';

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * What the schedule form needs
     *
     * The active schedule types with their rules (the API enforces them again on save), the
     * people who can be owners or participants, and what the signed-in user may set. Same
     * choices as the admin panel's schedule form.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $actor = $request->user();

        // each type with its rules (ScheduleTypeRules: the same ones the API enforces)
        $types = ScheduleTypeRules::activeTypes()
            ->map(fn(ScheduleType $type) => (new ScheduleTypeRules($type))->toArray())
            ->values();

        $people = User::query()
            ->where('status', true)
            ->whereNotNull('approved_at')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_url'])
            // the photo as a full URL, like the PWA's own user (null = show initials)
            ->map(function (User $person) {
                $avatar = $person->getFilamentAvatarUrl();

                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'avatar_url' => $avatar ? asset($avatar) : null,
                ];
            });

        return response()->json([
            'data' => [
                'types' => $types,
                'people' => $people,
                'statuses' => collect(ScheduleAccess::assignableStatuses($actor))
                    ->unique(fn(ScheduleStatus $status) => $status->value)
                    ->map(fn(ScheduleStatus $status) => ['value' => $status->value, 'label' => $status->getLabel()])
                    ->values(),
                'can' => [
                    'book_for_others' => $actor->can('create_any_schedule'),
                    'past_dates' => $actor->can('update_past_schedule'),
                    // whole-day schedules (no hours), on types that allow them
                    'all_day' => \App\Services\AllDayRule::mayBook($actor),
                    'internal_note' => ScheduleAccess::isStaff($actor),
                ],
                // dates without a timezone are read in this one
                'timezone' => config('app.timezone'),
            ],
        ]);
    }
}
