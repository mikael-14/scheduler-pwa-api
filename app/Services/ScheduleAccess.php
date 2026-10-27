<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
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
     * `update_status_schedule`: put any status on any schedule (not_shown once it started; never
     * completed, which schedules:complete sets). Neither lets the holder edit a schedule's content
     * (that is `update_schedule`).
     */
    public static function canApprove(User $user): bool
    {
        return $user->can('approve_schedule') || $user->can('update_status_schedule');
    }

    public static function canSetAnyStatus(User $user): bool
    {
        return $user->can('update_status_schedule');
    }

    /** Its start time has come (a whole-day schedule: its first day has begun). */
    public static function hasStarted(Schedule $schedule): bool
    {
        return $schedule->start !== null && $schedule->start->lte(now());
    }

    /**
     * The person who created it (or `update_any_schedule`) cancels a pending or approved schedule,
     * up to its start. Approvers reject instead; deleting is only done in the admin panel.
     */
    public static function canCancel(User $user, Schedule $schedule, ?ScheduleStatus $from = null): bool
    {
        return $user->can('update_schedule')
            && ($schedule->user_id === $user->id || $user->can('update_any_schedule'))
            && in_array($from ?? $schedule->status, [ScheduleStatus::Pending, ScheduleStatus::Approved], true)
            && ! static::hasStarted($schedule);
    }

    /** `update_status_schedule`: the owner of an approved (or completed) schedule did not come. */
    public static function canMarkNotShown(User $user, Schedule $schedule, ?ScheduleStatus $from = null): bool
    {
        return static::canSetAnyStatus($user)
            && static::hasStarted($schedule)
            && in_array($from ?? $schedule->status, [ScheduleStatus::Approved, ScheduleStatus::Completed], true);
    }

    /** The schedule's owner (or `update_status_schedule`): one of its approved participants did not come. */
    public static function canMarkParticipantNotShown(User $user, Schedule $schedule, ScheduleUser $participant, ?ScheduleStatus $from = null): bool
    {
        return ($schedule->user_id === $user->id || static::canSetAnyStatus($user))
            && static::hasStarted($schedule)
            && in_array($from ?? $participant->status, [ScheduleStatus::Approved, ScheduleStatus::Completed], true);
    }

    /**
     * Whether the user may move a schedule from one status to another, wherever it happens (API,
     * admin panel form, calendar, table actions). $from is null for a new schedule.
     * Completed is never chosen: only schedules:complete sets it.
     */
    public static function mayChangeStatus(User $user, Schedule $schedule, ?ScheduleStatus $from, ScheduleStatus $to): bool
    {
        if ($to === $from) {
            return true;
        }

        return match ($to) {
            ScheduleStatus::Pending => true,
            ScheduleStatus::Approved, ScheduleStatus::Rejected => static::canApprove($user),
            ScheduleStatus::Cancelled => static::canSetAnyStatus($user) || ($from && static::canCancel($user, $schedule, $from)),
            ScheduleStatus::NotShown => $from !== null && static::canMarkNotShown($user, $schedule, $from),
            ScheduleStatus::Completed => false,
        };
    }

    /** Participants: completed only by schedules:complete, not shown by the owner once it started. */
    public static function mayChangeParticipantStatus(User $user, ScheduleUser $participant, ?ScheduleStatus $from, ScheduleStatus $to): bool
    {
        if ($to === $from) {
            return true;
        }

        return match ($to) {
            ScheduleStatus::Completed => false,
            ScheduleStatus::NotShown => $from !== null
                && ($schedule = $participant->schedule()->first())
                && static::canMarkParticipantNotShown($user, $schedule, $participant, $from),
            default => true,
        };
    }

    /**
     * From Schedule::saving: a status change by a signed-in person must follow mayChangeStatus.
     * Tasks without a user (schedules:complete, seeders) are not limited.
     */
    public static function enforceStatusChange(Schedule $schedule): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ($schedule->exists && ! $schedule->isDirty('status'))) {
            return;
        }

        $from = $schedule->exists ? ScheduleStatus::tryFrom((string) $schedule->getRawOriginal('status')) : null;
        $to = $schedule->status;

        if ($to && ! static::mayChangeStatus($user, $schedule, $from, $to)) {
            static::failStatus(static::statusProblem($to));
        }
    }

    /** From ScheduleUser::saving: the same for a participant's status. */
    public static function enforceParticipantStatusChange(ScheduleUser $participant): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ($participant->exists && ! $participant->isDirty('status'))) {
            return;
        }

        $from = $participant->exists ? ScheduleStatus::tryFrom((string) $participant->getRawOriginal('status')) : null;
        $to = $participant->status;

        if ($to && ! static::mayChangeParticipantStatus($user, $participant, $from, $to)) {
            static::failStatus($to === ScheduleStatus::NotShown
                ? __('Only the owner can mark an approved participant as not shown, once the schedule has started.')
                : static::statusProblem($to));
        }
    }

    private static function statusProblem(ScheduleStatus $to): string
    {
        return match ($to) {
            ScheduleStatus::Completed => __('Completed is set automatically once a schedule is over.'),
            ScheduleStatus::NotShown => __('Only approved schedules that have started can be marked as not shown.'),
            ScheduleStatus::Cancelled => __('Only its creator can cancel a schedule, before it starts.'),
            default => __('You are not allowed to set this status.'),
        };
    }

    /**
     * In the admin panel (Livewire) the reason is shown as a notification and the save is halted;
     * everywhere else a ValidationException (HTTP 422 on the API).
     */
    private static function failStatus(string $problem): void
    {
        if (request()->hasHeader('X-Livewire') && class_exists(Halt::class)) {
            Notification::make()
                ->title(__('This status cannot be set'))
                ->body($problem)
                ->danger()
                ->send();

            throw new Halt();
        }

        throw ValidationException::withMessages(['status' => [$problem]]);
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

    /**
     * Statuses the user may put on a schedule; with the schedule, also by its time (cancel before
     * it starts, not shown after). Never completed: only schedules:complete sets it.
     */
    public static function assignableStatuses(User $user, ?ScheduleStatus $current = null, ?Schedule $schedule = null): array
    {
        $allowed = [ScheduleStatus::Pending];

        if (static::canSetAnyStatus($user) || ! $schedule || static::canCancel($user, $schedule)) {
            $allowed[] = ScheduleStatus::Cancelled;
        }

        if (static::canApprove($user)) {
            $allowed = [...$allowed, ScheduleStatus::Approved, ScheduleStatus::Rejected];
        }

        if (static::canSetAnyStatus($user) && (! $schedule || static::hasStarted($schedule))) {
            $allowed[] = ScheduleStatus::NotShown;
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
    public static function resolveStatus(User $user, mixed $requested, ?ScheduleStatus $current = null, ?Schedule $schedule = null): ScheduleStatus
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
        if (! in_array($status, static::assignableStatuses($user, $current, $schedule), true)) {
            throw ValidationException::withMessages([
                'status' => [__('You are not allowed to set this status.')],
            ]);
        }

        return $status;
    }
}
