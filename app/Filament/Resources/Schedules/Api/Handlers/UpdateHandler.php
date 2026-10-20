<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\Api\Transformers\ScheduleTransformer;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Http\Handlers\ApprovedHandler;
use App\Http\Handlers\Concerns\WritesSchedules;
use App\Services\PrimaryScheduleRule;
use App\Services\ScheduleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends ApprovedHandler
{
    use WritesSchedules;

    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleResource::class;
    // update_schedule edits content; update_status_schedule may change just the status of any schedule
    protected static string $permission = 'update_schedule|update_status_schedule';

    /** PATCH only validates the fields that are sent. */
    protected bool $partial = false;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Update Schedule
     *
     * Only the owner (or someone with `update_any_schedule`) can change a schedule.
     * Changing the slot of an approved schedule sends it back to `pending` unless the caller can approve.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $schedule = static::getModel()::with('schedule_users')->find($request->route('id'));

        if (! $schedule) {
            return static::sendNotFoundResponse();
        }

        $actor = $request->user();

        // A request that only carries `status` is a status change, governed by update_status_schedule
        $statusOnly = array_keys($request->all()) === ['status'];
        $mayEditContent = $actor->can('update_schedule') && ScheduleAccess::canUpdate($actor, $schedule);

        if (! ($statusOnly && ScheduleAccess::canSetAnyStatus($actor)) && ! $mayEditContent) {
            return $this->forbidden(__('You can only change your own schedules.'));
        }

        $data = $request->validate($this->scheduleRules($this->partial));
        $staff = ScheduleAccess::isStaff($actor);
        $canApprove = $actor->can('approve_schedule') || $actor->can('update_status_schedule');

        return DB::transaction(function () use ($data, $actor, $schedule, $staff, $canApprove) {
            $originalStatus = $schedule->status;

            $schedule->fill(Arr::only($data, ['start', 'end', 'all_day', 'description', 'schedule_type_id']));

            if (array_key_exists('user_id', $data) && $data['user_id'] && $actor->can('update_any_schedule')) {
                $schedule->user_id = (int) $data['user_id'];
            }

            if ($staff && array_key_exists('internal_note', $data)) {
                $schedule->internal_note = $data['internal_note'];
            }

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $schedule->status = ScheduleAccess::resolveStatus($actor, $data['status'], $originalStatus);
            } elseif (
                ! $canApprove
                && $originalStatus === ScheduleStatus::Approved
                && $schedule->isDirty(['start', 'end', 'all_day', 'schedule_type_id'])
            ) {
                // The approval was for a different slot
                $schedule->status = ScheduleStatus::Pending;
            }

            // participants are stored after the schedule; the primary rule counts them already
            PrimaryScheduleRule::withCompanions(
                array_column($data['schedule_users'] ?? [], 'user_id'),
                fn() => $schedule->save()
            );

            // Participants follow the schedule when it is approved or rejected
            if ($schedule->wasChanged('status')) {
                if ($schedule->status === ScheduleStatus::Approved) {
                    $schedule->schedule_users()->where('status', ScheduleStatus::Pending)
                        ->update(['status' => ScheduleStatus::Approved]);
                } elseif ($schedule->status === ScheduleStatus::Rejected) {
                    $schedule->schedule_users()->update(['status' => ScheduleStatus::Rejected]);
                }
            }

            if (array_key_exists('schedule_users', $data) && $data['schedule_users'] !== null) {
                $this->syncParticipants($schedule, $data['schedule_users'], $actor, true);
            }

            return static::sendSuccessResponse(
                new ScheduleTransformer($schedule->fresh('schedule_users')),
                'Successfully Update Resource'
            );
        });
    }
}
