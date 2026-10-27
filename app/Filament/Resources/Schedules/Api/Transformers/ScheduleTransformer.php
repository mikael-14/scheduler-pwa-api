<?php
namespace App\Filament\Resources\Schedules\Api\Transformers;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Services\PrimaryScheduleRule;
use App\Services\ScheduleAccess;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Schedule $resource
 */
class ScheduleTransformer extends JsonResource
{
    /** Relations the transformer reads; load them with the query to avoid one query per row. */
    public const RELATIONS = [
        'schedule_users.user:id,name,avatar_url',
        'schedule_type:id,name,color,all_day,range',
        'user:id,name,avatar_url',
    ];

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $this->resource->loadMissing(self::RELATIONS);
        $data = $this->resource->toArray();

        // The internal note is for staff only, never for the people on the shared agenda
        $user = $request->user();
        if (! $user || ! ScheduleAccess::isStaff($user)) {
            unset($data['internal_note']);
        }

        // Names and colours, so an app can show the schedule without looking anything else up
        $type = $this->resource->schedule_type;
        $data['schedule_type'] = $type ? $type->only(['id', 'name', 'color', 'all_day', 'range']) : null;
        $owner = $this->resource->user;
        $data['user'] = $owner
            ? ['id' => $owner->id, 'name' => $owner->name, 'avatar_url' => $this->avatarUrl($owner)]
            : null;

        $approver = $user && ScheduleAccess::canApprove($user);

        $data['schedule_users'] = $this->resource->schedule_users->map(function ($scheduleUser) use ($approver, $user) {
            $row = [
                'id' => $scheduleUser->id,
                'user_id' => $scheduleUser->user_id,
                'name' => $scheduleUser->user?->name,
                'avatar_url' => $scheduleUser->user ? $this->avatarUrl($scheduleUser->user) : null,
                'status' => $scheduleUser->status,
                'description' => $scheduleUser->description,
                // the owner (or update_status_schedule), once it started: this person did not come
                'can_mark_not_shown' => $user
                    && ScheduleAccess::canMarkParticipantNotShown($user, $this->resource, $scheduleUser),
            ];

            // approvers: why a pending participation cannot be approved yet (primary rule), or null
            if ($approver && $scheduleUser->status === ScheduleStatus::Pending) {
                $row['approval_problem'] = PrimaryScheduleRule::participantProblem($scheduleUser);
            }

            return $row;
        });

        $data['can'] = $user ? $this->abilities($user) : [];

        // approvers: why this pending schedule cannot be approved yet (primary rule), or null
        if ($approver && $this->resource->status === ScheduleStatus::Pending) {
            $data['approval_problem'] = PrimaryScheduleRule::scheduleProblem($this->resource);
        }

        return $data;
    }

    /** A person's photo as a full URL (null = the app shows their initials). */
    private function avatarUrl(User $person): ?string
    {
        $avatar = $person->getFilamentAvatarUrl();

        return $avatar ? asset($avatar) : null;
    }

    /**
     * What the signed-in user may do with this schedule, by the same rules the API enforces,
     * so an app only offers the actions that will work.
     */
    private function abilities($user): array
    {
        $schedule = $this->resource;
        $status = $schedule->status;
        $update = $user->can('update_schedule') && ScheduleAccess::canUpdate($user, $schedule);

        return [
            'update' => $update,
            // its creator, before it starts (deleting is only done in the admin panel)
            'cancel' => ScheduleAccess::canCancel($user, $schedule),
            'approve' => ScheduleAccess::canApprove($user) && $status === ScheduleStatus::Pending,
            // update_status_schedule, once it started: its owner did not come
            'not_shown' => ScheduleAccess::canMarkNotShown($user, $schedule),
            // join / leave someone else's schedule (participate_schedule / cancel_participation_schedule)
            'join' => ScheduleAccess::canJoin($user, $schedule),
            'leave' => ScheduleAccess::canLeave($user, $schedule),
            // decide on pending participants (people who joined)
            'approve_participants' => ScheduleAccess::canApprove($user)
                && $schedule->schedule_users->contains('status', ScheduleStatus::Pending),
        ];
    }
}
