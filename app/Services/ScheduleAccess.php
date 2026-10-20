<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Who may do what with a schedule, for the API and the admin panel (SchedulePolicy).
 *
 * - `view_any_schedule` opens the shared agenda; without `view_inactive_schedule` it only shows
 *   pending / approved schedules and the person's own.
 * - A person manages their own schedules from today on; other people's need the *_any_schedule
 *   permissions, past ones `update_past_schedule`.
 * - `participate_schedule` / `cancel_participation_schedule`: join / leave other people's schedules.
 * - Approving / rejecting / setting any status is limited to the approval permissions, and the
 *   primary type has its own rule (PrimaryScheduleRule).
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

    /**
     * What the shared agenda shows. With `view_inactive_schedule`, everything. Otherwise only
     * pending and approved schedules, plus the person's own and the ones they take part in
     * (whatever their status).
     */
    public static function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->can('view_inactive_schedule')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Approved])
                ->orWhere('user_id', $user->id)
                ->orWhereHas('schedule_users', fn(Builder $su) => $su->where('user_id', $user->id));
        });
    }

    public static function canView(User $user, Schedule $schedule): bool
    {
        return $user->can('view_inactive_schedule')
            || in_array($schedule->status, [ScheduleStatus::Pending, ScheduleStatus::Approved], true)
            || static::isOwnerOrParticipant($user, $schedule);
    }

    /** Started before today: only `update_past_schedule` may change it or take part in it. */
    public static function isPastLocked(User $user, Schedule $schedule): bool
    {
        $tz = config('app.timezone');

        return $schedule->start
            && $schedule->start->copy()->setTimezone($tz)->lt(now($tz)->startOfDay())
            && ! $user->can('update_past_schedule');
    }

    /** Own schedules (or anyone's with `update_any_schedule`), from today on. */
    public static function canUpdate(User $user, Schedule $schedule): bool
    {
        return ($schedule->user_id === $user->id || $user->can('update_any_schedule'))
            && ! static::isPastLocked($user, $schedule);
    }

    /**
     * `participate_schedule`: join someone else's pending or approved schedule, from today on.
     * The participation starts pending and is approved like a schedule.
     */
    public static function canJoin(User $user, Schedule $schedule): bool
    {
        return $user->can('participate_schedule')
            && $schedule->user_id !== $user->id
            && in_array($schedule->status, [ScheduleStatus::Pending, ScheduleStatus::Approved], true)
            && ! static::isPastLocked($user, $schedule)
            && ! static::participation($user, $schedule);
    }

    /** `cancel_participation_schedule`: leave a schedule they take part in, from today on. */
    public static function canLeave(User $user, Schedule $schedule): bool
    {
        return $user->can('cancel_participation_schedule')
            && ! static::isPastLocked($user, $schedule)
            && (bool) static::participation($user, $schedule);
    }

    /** The user's participation in the schedule, if any. */
    public static function participation(User $user, Schedule $schedule): ?ScheduleUser
    {
        return $schedule->relationLoaded('schedule_users')
            ? $schedule->schedule_users->firstWhere('user_id', $user->id)
            : $schedule->schedule_users()->where('user_id', $user->id)->first();
    }

    public static function isOwnerOrParticipant(User $user, Schedule $schedule): bool
    {
        return $schedule->user_id === $user->id || (bool) static::participation($user, $schedule);
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
        return ($schedule->user_id === $user->id || $user->can('delete_any_schedule'))
            && ! static::isPastLocked($user, $schedule);
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
