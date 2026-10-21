<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Models\ScheduleUser;
use App\Models\User;
use App\Services\PrimaryScheduleRule;
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
     * `participations` lists people who joined an already decided schedule; decide on them with
     * `POST /{id}/participants/{userId}/approve` or `/reject`.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $schedules = static::getModel()::query()
            ->where('status', ScheduleStatus::Pending)
            ->with(['user:id,name,avatar_url', 'schedule_type:id,name,color', 'schedule_users.user:id,name,avatar_url'])
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
                'user' => $this->person($schedule->user),
                'schedule_type' => $schedule->schedule_type
                    ? $schedule->schedule_type->only(['id', 'name', 'color'])
                    : null,
                'participants' => $schedule->schedule_users
                    ->map(fn($su) => $su->user?->name)
                    ->filter()
                    ->values(),
                // the same people with their photos (`participants` stays names only for older apps)
                'participant_people' => $schedule->schedule_users
                    ->map(fn($su) => $this->person($su->user))
                    ->filter()
                    ->values(),
                // primary type: why it cannot be approved yet (null = it can)
                'approval_problem' => PrimaryScheduleRule::scheduleProblem($schedule),
            ])->values(),
            // people who joined an approved schedule, waiting for their own decision
            'participations' => ScheduleUser::query()
                ->where('status', ScheduleStatus::Pending)
                // on a pending schedule they are decided with it
                ->whereHas('schedule', fn($query) => $query->where('status', ScheduleStatus::Approved))
                ->with(['user:id,name,avatar_url', 'schedule.user:id,name,avatar_url', 'schedule.schedule_type:id,name,color,primary'])
                ->limit(200)
                ->get()
                ->filter(fn(ScheduleUser $participant) => $participant->schedule)
                ->sortBy(fn(ScheduleUser $participant) => $participant->schedule->start)
                ->map(fn(ScheduleUser $participant) => [
                    'schedule_id' => $participant->schedule_id,
                    'user' => $this->person($participant->user),
                    'start' => $participant->schedule->start,
                    'end' => $participant->schedule->end,
                    'all_day' => $participant->schedule->all_day,
                    'schedule_status' => $participant->schedule->status,
                    'owner' => $this->person($participant->schedule->user),
                    'schedule_type' => $participant->schedule->schedule_type
                        ? $participant->schedule->schedule_type->only(['id', 'name', 'color'])
                        : null,
                    'approval_problem' => PrimaryScheduleRule::participantProblem($participant),
                ])->values(),
        ]);
    }

    /** Id, name and photo (as a full URL; null = the app shows initials), nothing else about the person. */
    private function person(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $avatar = $user->getFilamentAvatarUrl();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $avatar ? asset($avatar) : null,
        ];
    }
}
