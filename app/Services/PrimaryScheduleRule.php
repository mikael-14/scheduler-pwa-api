<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\ScheduleUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The approval rule of the primary schedule type (going to the shelter), in one place for the
 * admin panel, the API and the PWA:
 *
 *   Someone who may not go alone (no `independent_schedule`, e.g. "Voluntário Novo") can only be
 *   approved when someone who may (`independent_schedule`, e.g. Admin, "Voluntário Experiente")
 *   is already there when they arrive: approved, starting at the same time or earlier, and still
 *   there at their start (until the end time, or for one hour when no end was given).
 *   Experiente 9h + Novo 10h: approved. Novo 9h + Experiente 10h: not (alone for an hour).
 *
 * "Going" = on an approved schedule of the primary type, as its owner or as an approved
 * participant. People on the same schedule go together: a Novo booking with an Experiente
 * participant is approved in one go. Whole-day schedules (no hours) cover nobody, and nobody who
 * may not go alone is approved on one.
 *
 * Bookings are never refused: they stay pending, and only approving them is blocked.
 * Other schedule types are not affected by this, only by the whole-day rule (AllDayRule), which
 * is checked here too so every approval path and every "why not" message has both.
 *
 * Enforced from the models' `saving` hooks (Schedule, ScheduleUser), so every way of approving
 * obeys it: the approve / reject actions, status fields, the API and the PWA.
 */
class PrimaryScheduleRule
{
    public const PERMISSION = 'independent_schedule';

    /**
     * Extra people known to be on the schedule being saved, before they are stored (a form
     * that creates the schedule and its participants together). See withCompanions().
     *
     * @var array<int, int>
     */
    private static array $companions = [];

    /** Set while approved schedules are being put back to pending (see recheck()). */
    private static bool $rechecking = false;

    public static function mayGoAlone(?User $user): bool
    {
        return (bool) $user?->can(self::PERMISSION);
    }

    /**
     * The users about to be added to the schedule being saved (see withCompanions), e.g. so the
     * activity feed does not tell them about a booking they are part of.
     *
     * @return list<int>
     */
    public static function companionIds(): array
    {
        return self::$companions;
    }

    /**
     * Runs $callback knowing that these users are about to be added to the schedule being saved
     * (their rows are written after the schedule itself).
     */
    public static function withCompanions(array $userIds, callable $callback): mixed
    {
        $previous = self::$companions;
        self::$companions = array_values(array_filter(array_map('intval', $userIds)));

        try {
            return $callback();
        } finally {
            self::$companions = $previous;
        }
    }

    /**
     * Why this schedule cannot be approved, or null when it can. Checks the owner and the
     * participants approved along with it (pending ones), or, with $approvedOnly (re-checking an
     * approved schedule), only the participants already approved.
     */
    public static function scheduleProblem(Schedule $schedule, bool $approvedOnly = false): ?string
    {
        // approved ones are only re-checked for who covers them (the whole-day rule held when approved)
        if (! $approvedOnly && $problem = AllDayRule::problem($schedule)) {
            return $problem;
        }

        if (! static::applies($schedule)) {
            return null;
        }

        $people = static::peopleOn($schedule, $approvedOnly);
        $alone = $people->reject(fn(User $user) => static::mayGoAlone($user));

        if ($alone->isEmpty()) {
            return null;
        }

        if ($schedule->all_day) {
            return static::allDayMessage($alone);
        }

        if ($people->contains(fn(User $user) => static::mayGoAlone($user))) {
            return null;
        }

        if (static::someoneResponsibleElsewhere($schedule)) {
            return null;
        }

        return static::message($alone);
    }

    /** Why this participant cannot be approved, or null when they can. */
    public static function participantProblem(ScheduleUser $participant): ?string
    {
        $schedule = $participant->schedule;
        $user = $participant->user;

        if (! $schedule || ! $user || ! static::applies($schedule) || static::mayGoAlone($user)) {
            return null;
        }

        if ($schedule->all_day) {
            return static::allDayMessage(collect([$user]));
        }

        // with the schedule's own people, once that schedule is approved
        if ($schedule->status === ScheduleStatus::Approved
            && static::peopleOn($schedule)->contains(fn(User $other) => $other->id !== $user->id && static::mayGoAlone($other))) {
            return null;
        }

        if (static::someoneResponsibleElsewhere($schedule)) {
            return null;
        }

        return static::message(collect([$user]));
    }

    /** From Schedule::saving: only when the schedule becomes approved. */
    public static function enforceOnSchedule(Schedule $schedule): void
    {
        if ($schedule->status !== ScheduleStatus::Approved) {
            return;
        }

        if (! $schedule->exists || $schedule->isDirty('status')) {
            static::fail(static::scheduleProblem($schedule));
        } elseif ($schedule->isDirty(['start', 'end', 'all_day', 'schedule_type_id'])) {
            // an approved schedule moved by an approver: not onto a day taken by a whole-day one
            static::fail(AllDayRule::problem($schedule));
        }
    }

    /** From ScheduleUser::saving: only when the participant becomes approved. */
    public static function enforceOnParticipant(ScheduleUser $participant): void
    {
        if ($participant->status !== ScheduleStatus::Approved || ($participant->exists && ! $participant->isDirty('status'))) {
            return;
        }

        static::fail(static::participantProblem($participant));
    }

    /**
     * From Schedule::updated / deleted. When an approved primary schedule stops counting
     * (cancelled, rejected, back to pending, deleted, moved, handed to someone else), the approved
     * schedules that relied on it at those hours are checked again.
     */
    public static function afterScheduleChange(Schedule $schedule, bool $deleted = false): void
    {
        $wasApproved = ($schedule->getOriginal('status') ?? $schedule->status) === ScheduleStatus::Approved;
        $changed = $deleted || $schedule->wasChanged(['status', 'start', 'end', 'all_day', 'schedule_type_id', 'user_id']);

        // completed / not shown only records what happened: the others at those hours stay as they are
        if (! $wasApproved || ! $changed || (! $deleted && $schedule->status->isOutcome())) {
            return;
        }

        // the arrivals it covered before the change (all that can have been lost)
        $before = (new Schedule())->setRawAttributes($schedule->getRawOriginal());
        if (static::applies($before) && $covered = static::coverage($before)) {
            static::recheck($covered);
        }

        // still approved but moved: the new hours need someone too (it is re-checked itself there)
        if (! $deleted && $schedule->status === ScheduleStatus::Approved && static::applies($schedule)) {
            static::recheck(static::coverage($schedule) ?? static::window($schedule));
        }
    }

    /** From ScheduleUser::updated / deleted: an approved participant left or stopped counting. */
    public static function afterParticipantChange(ScheduleUser $participant, bool $deleted = false): void
    {
        $wasApproved = ($participant->getOriginal('status') ?? $participant->status) === ScheduleStatus::Approved;

        if (! $wasApproved || (! $deleted && (! $participant->wasChanged('status') || $participant->status->isOutcome()))) {
            return;
        }

        $schedule = $participant->schedule()->first();
        if (! $schedule || ! static::applies($schedule)) {
            return;
        }

        static::recheck(static::coverage($schedule) ?? static::window($schedule));
    }

    /**
     * Approved primary schedules starting in this time that nobody who may go alone covers any
     * more go back to pending (the schedule that changed is among them when it starts there);
     * their people are told, and approvers get them to decide again (ScheduleObserver). The ones
     * put back do not trigger further checks: with nobody who may go alone on them, they never
     * covered anyone.
     *
     * @param array{0: CarbonInterface, 1: CarbonInterface} $window
     */
    private static function recheck(array $window): void
    {
        if (self::$rechecking) {
            return;
        }

        [$from, $to] = $window;
        self::$rechecking = true;

        try {
            Schedule::query()
                ->where('status', ScheduleStatus::Approved)
                ->whereHas('schedule_type', fn($query) => $query->where('primary', true))
                ->where('start', '<=', $to->copy()->endOfDay())
                ->where('start', '>=', $from->copy()->startOfDay())
                ->get()
                ->filter(function (Schedule $schedule) use ($from, $to) {
                    [$arrival] = static::window($schedule);

                    return $arrival->gte($from) && $arrival->lte($to);
                })
                ->each(function (Schedule $schedule) {
                    if (static::scheduleProblem($schedule, approvedOnly: true) === null) {
                        return;
                    }

                    $schedule->update(['status' => ScheduleStatus::Pending]);
                    static::tellBackToPending($schedule);
                });
        } finally {
            self::$rechecking = false;
        }
    }

    private static function tellBackToPending(Schedule $schedule): void
    {
        $schedule->load(['user', 'schedule_type', 'schedule_users.user']);

        collect([$schedule->user])
            ->merge($schedule->schedule_users->pluck('user'))
            ->filter(fn(?User $user) => $user && $user->status && $user->approved_at && $user->id !== auth()->id())
            ->unique('id')
            ->each(function (User $user) use ($schedule) {
                try {
                    $user->notify(new \App\Notifications\ScheduleBackToPending($schedule));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }

    /**
     * The time a schedule takes, in the app's timezone. All-day schedules take their whole days.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function window(Schedule $schedule): array
    {
        $tz = config('app.timezone');
        $start = $schedule->start->copy()->setTimezone($tz);
        $end = $schedule->end?->copy()->setTimezone($tz);

        if ($schedule->all_day) {
            return [$start->copy()->startOfDay(), ($end ?? $start)->copy()->endOfDay()];
        }

        return [$start, $end && $end->gt($start) ? $end : $start->copy()->addMinute()];
    }

    /**
     * When someone on this schedule (who may go alone) is there for others to arrive: from its
     * start until its end, or for one hour when it has no end. Null for whole-day schedules,
     * whose hours are unknown.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public static function coverage(Schedule $schedule): ?array
    {
        if ($schedule->all_day || ! $schedule->start) {
            return null;
        }

        $tz = config('app.timezone');
        $start = $schedule->start->copy()->setTimezone($tz);
        $end = $schedule->end?->copy()->setTimezone($tz);

        return [$start, $end && $end->gt($start) ? $end : $start->copy()->addHour()];
    }

    private static function applies(Schedule $schedule): bool
    {
        // by id: the loaded relation may still be the old type while the type is being changed
        return $schedule->start
            && $schedule->schedule_type_id
            && (bool) ScheduleType::query()->whereKey($schedule->schedule_type_id)->value('primary');
    }

    /**
     * The owner and everyone going with them on this schedule (participants not rejected or
     * cancelled, plus the ones about to be added).
     *
     * @return Collection<int, User>
     */
    private static function peopleOn(Schedule $schedule, bool $approvedOnly = false): Collection
    {
        $ids = collect([$schedule->user_id]);

        if ($schedule->exists) {
            $ids = $ids->merge($schedule->schedule_users()
                ->whereIn('status', $approvedOnly ? [ScheduleStatus::Approved] : [ScheduleStatus::Pending, ScheduleStatus::Approved])
                ->pluck('user_id'));
        }

        $ids = $ids->merge(self::$companions)->filter()->unique();

        return User::query()->whereIn('id', $ids)->get();
    }

    /**
     * Someone who may go alone is on another approved primary schedule and already there when
     * this one starts (see coverage()).
     */
    private static function someoneResponsibleElsewhere(Schedule $schedule): bool
    {
        if ($schedule->all_day) {
            return false;
        }

        [$arrival] = static::window($schedule);

        $others = Schedule::query()
            ->with(['user', 'schedule_users' => fn($query) => $query->where('status', ScheduleStatus::Approved)->with('user')])
            ->where('status', ScheduleStatus::Approved)
            ->where('all_day', false)
            ->whereHas('schedule_type', fn($query) => $query->where('primary', true))
            ->when($schedule->exists, fn($query) => $query->whereKeyNot($schedule->getKey()))
            // candidates starting that day up to the arrival; the exact hours are compared below
            ->where('start', '<=', $arrival)
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [$arrival->copy()->subHour()->startOfDay()])
            ->get();

        return $others->contains(function (Schedule $other) use ($arrival) {
            [$coveredFrom, $coveredTo] = static::coverage($other) ?? [null, null];

            if (! $coveredFrom || $arrival->lt($coveredFrom) || $arrival->gt($coveredTo)) {
                return false;
            }

            return static::mayGoAlone($other->user)
                || $other->schedule_users->contains(fn(ScheduleUser $participant) => static::mayGoAlone($participant->user));
        });
    }

    private static function message(Collection $people): string
    {
        return __(':names cannot go alone: someone who may go alone must be approved first, starting at the same time or up to an hour earlier (or until their end time).', [
            'names' => $people->pluck('name')->implode(', '),
        ]);
    }

    private static function allDayMessage(Collection $people): string
    {
        return __(':names cannot go alone, and a whole-day schedule has no hours to go with someone: it needs a start time.', [
            'names' => $people->pluck('name')->implode(', '),
        ]);
    }

    /**
     * In the admin panel (Livewire) the reason is shown as a notification and the save is halted;
     * everywhere else a ValidationException (HTTP 422 on the API).
     */
    public static function fail(?string $problem): void
    {
        if ($problem === null) {
            return;
        }

        if (request()->hasHeader('X-Livewire') && class_exists(Halt::class)) {
            Notification::make()
                ->title(__('Cannot be approved yet'))
                ->body($problem)
                ->danger()
                ->send();

            throw new Halt();
        }

        throw ValidationException::withMessages(['status' => [$problem]]);
    }
}
