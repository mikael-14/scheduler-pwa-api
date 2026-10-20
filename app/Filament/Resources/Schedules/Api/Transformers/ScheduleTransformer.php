<?php
namespace App\Filament\Resources\Schedules\Api\Transformers;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
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
        'schedule_users.user:id,name',
        'schedule_type:id,name,color,all_day,range',
        'user:id,name',
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
        $data['user'] = $owner ? ['id' => $owner->id, 'name' => $owner->name] : null;

        $approver = $user && ScheduleAccess::canApprove($user);

        $data['schedule_users'] = $this->resource->schedule_users->map(function ($scheduleUser) use ($approver) {
            $row = [
                'id' => $scheduleUser->id,
                'user_id' => $scheduleUser->user_id,
                'name' => $scheduleUser->user?->name,
                'status' => $scheduleUser->status,
                'description' => $scheduleUser->description,
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

    /**
     * What the signed-in user may do with this schedule, by the same rules the API enforces,
     * so an app only offers the actions that will work.
     */
    private function abilities($user): array
    {
        $schedule = $this->resource;
        $status = $schedule->status;
        $update = $user->can('update_schedule') && ScheduleAccess::canUpdate($user, $schedule);
        $deletable = $user->can('delete_any_schedule')
            || in_array($status, [ScheduleStatus::Pending, ScheduleStatus::Cancelled], true);

        return [
            'update' => $update,
            'cancel' => $update && in_array($status, [ScheduleStatus::Pending, ScheduleStatus::Approved], true),
            'delete' => $user->can('delete_schedule') && ScheduleAccess::canDelete($user, $schedule) && $deletable,
            'approve' => ScheduleAccess::canApprove($user) && $status === ScheduleStatus::Pending,
            // join / leave someone else's schedule (participate_schedule / cancel_participation_schedule)
            'join' => ScheduleAccess::canJoin($user, $schedule),
            'leave' => ScheduleAccess::canLeave($user, $schedule),
            // decide on pending participants (people who joined)
            'approve_participants' => ScheduleAccess::canApprove($user)
                && $schedule->schedule_users->contains('status', ScheduleStatus::Pending),
        ];
    }
}
