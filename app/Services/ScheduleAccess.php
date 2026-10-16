<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Who may do what with a schedule through the API.
 *
 * - Everyone approved with `view_any_schedule` can see the shared agenda.
 * - A person manages their own schedules; managing other people's needs the *_any_schedule permissions.
 * - Approving / rejecting / setting any status is limited to the approval permissions.
 */
class ScheduleAccess
{
    /** Approvers and admins: may read internal notes and touch other people's schedules. */
    public static function isStaff(User $user): bool
    {
        return $user->can('approve_schedule')
            || $user->can('update_status_schedule')
            || $user->can('update_any_schedule');
    }

    public static function canUpdate(User $user, Schedule $schedule): bool
    {
        return $schedule->user_id === $user->id || $user->can('update_any_schedule');
    }

    /**
     * `approve_schedule`: approve or reject pending schedules and nothing else.
     * `update_status_schedule`: put any status on any schedule, e.g. completed or not_shown.
     * Neither lets the holder edit a schedule's content (that is `update_schedule`).
     */
    public static function canApprove(User $user): bool
    {
        return $user->can('approve_schedule') || $user->can('update_status_schedule');
    }

    public static function canSetAnyStatus(User $user): bool
    {
        return $user->can('update_status_schedule');
    }

    public static function canDelete(User $user, Schedule $schedule): bool
    {
        return $schedule->user_id === $user->id || $user->can('delete_any_schedule');
    }

    /** Whose schedule is being created: only `create_any_schedule` may book for someone else. */
    public static function ownerForCreate(User $user, mixed $requestedUserId): int
    {
        if ($requestedUserId && $user->can('create_any_schedule')) {
            return (int) $requestedUserId;
        }

        return $user->id;
    }

    /** Statuses the user may put on a schedule. */
    public static function assignableStatuses(User $user, ?ScheduleStatus $current = null): array
    {
        if ($user->can('update_status_schedule')) {
            return ScheduleStatus::cases();
        }

        $allowed = [ScheduleStatus::Pending, ScheduleStatus::Cancelled];

        if ($user->can('approve_schedule')) {
            $allowed = [...$allowed, ScheduleStatus::Approved, ScheduleStatus::Rejected];
        }

        if ($current) {
            $allowed[] = $current;
        }

        return $allowed;
    }

    /**
     * Resolve the status to store. A missing status means "pending" on create, "unchanged" on update.
     *
     * @throws ValidationException
     */
    public static function resolveStatus(User $user, mixed $requested, ?ScheduleStatus $current = null): ScheduleStatus
    {
        $default = $current ?? ScheduleStatus::Pending;

        if ($requested === null || $requested === '') {
            return $default;
        }

        $status = $requested instanceof ScheduleStatus ? $requested : ScheduleStatus::from($requested);

        if ($status === $default && $current) {
            return $status;
        }

        // Plain users cannot self-approve; approvers choose within their permissions
        if (! in_array($status, static::assignableStatuses($user, $current), true)) {
            throw ValidationException::withMessages([
                'status' => [__('You are not allowed to set this status.')],
            ]);
        }

        return $status;
    }
}
