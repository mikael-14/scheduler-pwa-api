<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleTypeSlot;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shift slots: a schedule type with `uses_slots` offers fixed times (ScheduleTypeSlot) that
 * people pick instead of choosing their own hours, from the PWA and the admin panel.
 *
 * - One schedule per slot: the first person to take it creates it (and owns it), the next ones
 *   join it as participants. Approval, the primary rule and the notifications are the usual ones.
 * - A slot may take a maximum of people (owner included); a full slot can't be taken or joined.
 * - Its owner leaving hands it to the next person on it, so a shift is not cancelled for everyone.
 */
class ScheduleSlots
{
    /** Take a place on the slot: its schedule is created, or joined. */
    public static function take(User $user, ScheduleTypeSlot $slot): Schedule
    {
        return DB::transaction(function () use ($user, $slot) {
            // one at a time per slot: two people taking it at once must not make two schedules
            $slot = ScheduleTypeSlot::query()->whereKey($slot->getKey())->lockForUpdate()->firstOrFail();
            $schedule = $slot->activeSchedule()?->load('schedule_users');

            static::fail(static::problem($user, $slot, $schedule));

            if ($schedule) {
                ScheduleParticipation::join($user, $schedule);

                return $schedule;
            }

            $schedule = new Schedule([
                'schedule_type_id' => $slot->schedule_type_id,
                'schedule_type_slot_id' => $slot->id,
                'start' => $slot->start,
                'end' => $slot->end,
                'all_day' => false,
            ]);
            $schedule->user_id = $user->id;
            $schedule->status = ScheduleStatus::Pending;
            $schedule->save();

            return $schedule;
        });
    }

    /**
     * Admin panel (create page, calendar modal): the slot picked in the schedule form, for its
     * owner (someone else only with create_any_schedule). Null when no slot was picked.
     */
    public static function takeFromForm(array $data): ?Schedule
    {
        $slot = filled($data['schedule_type_slot_id'] ?? null) ? ScheduleTypeSlot::find($data['schedule_type_slot_id']) : null;
        if (! $slot) {
            return null;
        }

        $actor = auth()->user();
        $owner = $actor;
        if (filled($data['user_id'] ?? null) && (int) $data['user_id'] !== $actor?->id && ScheduleAccess::maySetOwner($actor)) {
            $owner = User::find($data['user_id']) ?? $actor;
        }

        $schedule = static::take($owner, $slot);

        // a new booking keeps what was written in the form
        if ($schedule->wasRecentlyCreated && filled($data['description'] ?? null)) {
            $schedule->update(['description' => $data['description']]);
        }

        return $schedule;
    }

    /** Why the person can't take the slot, or null when they can. */
    public static function problem(User $user, ScheduleTypeSlot $slot, ?Schedule $schedule = null): ?string
    {
        $type = $slot->schedule_type;

        if (! $type || ! $type->status || ! $type->uses_slots) {
            return __('This schedule type is not active.');
        }

        if ($slot->start->lte(now()) && ! $user->can('update_past_schedule')) {
            return __('This slot has already started.');
        }

        if ($schedule && ($schedule->user_id === $user->id || ScheduleAccess::participation($user, $schedule))) {
            return __('You are already on this slot.');
        }

        if ($slot->isFull($schedule)) {
            return __('This slot is full.');
        }

        if (! $schedule) {
            return $user->can('create_schedule') ? null : __('This action is unauthorized.');
        }

        return ScheduleAccess::canJoin($user, $schedule) ? null : __('You cannot join this schedule.');
    }

    /**
     * The owner leaves their slot schedule: the person who joined first becomes its owner, and the
     * one leaving stays on it as cancelled (the others are told they left). Nobody else on it:
     * the schedule is cancelled.
     */
    public static function ownerLeaves(User $user, Schedule $schedule): void
    {
        DB::transaction(function () use ($user, $schedule) {
            $next = $schedule->schedule_users()
                ->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Approved])
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            if (! $next) {
                $schedule->update(['status' => ScheduleStatus::Cancelled]);

                return;
            }

            // their participation makes way (Schedule::updated); the primary rule checks it again
            ScheduleAccess::handingOver(fn() => $schedule->update(['user_id' => $next->user_id]));

            $left = ScheduleUser::withoutEvents(fn() => ScheduleUser::create([
                'schedule_id' => $schedule->id,
                'user_id' => $user->id,
                'status' => $schedule->fresh()->status,
            ]));
            $left->delete(); // cancelled, like leaving: told to the others
        });
    }

    /**
     * The slots of a type that have not ended, as the PWA offers them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listFor(User $user, int $typeId): array
    {
        $slots = ScheduleTypeSlot::query()
            ->with('schedule_type')
            ->where('schedule_type_id', $typeId)
            ->where('end', '>', now())
            ->orderBy('start')
            ->get();

        $schedules = Schedule::query()
            ->with('schedule_users')
            ->whereIn('schedule_type_slot_id', $slots->pluck('id'))
            ->whereIn('status', [ScheduleStatus::Pending, ScheduleStatus::Approved])
            ->orderByDesc('id')
            ->get()
            ->unique('schedule_type_slot_id')
            ->keyBy('schedule_type_slot_id');

        return $slots->map(function (ScheduleTypeSlot $slot) use ($user, $schedules) {
            $schedule = $schedules->get($slot->id);
            $people = $schedule ? ScheduleTypeSlot::peopleOn($schedule) : 0;
            $mine = $schedule && ($schedule->user_id === $user->id
                || $schedule->schedule_users->contains(fn($participant) => $participant->user_id === $user->id
                    && in_array($participant->status, [ScheduleStatus::Pending, ScheduleStatus::Approved], true)));

            return [
                'id' => $slot->id,
                'start' => $slot->start,
                'end' => $slot->end,
                'comment' => $slot->comment,
                'capacity' => $slot->capacity,
                'people' => $people,
                'full' => $slot->capacity !== null && $people >= $slot->capacity,
                'schedule_id' => $schedule?->id,
                'mine' => $mine,
                // why it can't be taken (null = it can)
                'problem' => $mine ? null : static::problem($user, $slot, $schedule),
            ];
        })->values()->all();
    }

    /** For a schedule booked on a slot: what apps and the admin panel show about the slot. */
    public static function summary(Schedule $schedule): ?array
    {
        $slot = $schedule->schedule_type_slot_id
            ? ($schedule->relationLoaded('slot') ? $schedule->slot : $schedule->slot()->first())
            : null;

        if (! $slot) {
            return null;
        }

        return [
            'id' => $slot->id,
            'comment' => $slot->comment,
            'capacity' => $slot->capacity,
            'people' => ScheduleTypeSlot::peopleOn($schedule),
        ];
    }

    /**
     * In the admin panel (Livewire) the problem is shown as a notification and the action stops;
     * everywhere else a ValidationException (HTTP 422 on the API, under `slot`).
     */
    private static function fail(?string $problem): void
    {
        if ($problem === null) {
            return;
        }

        if (request()->hasHeader('X-Livewire') && class_exists(Halt::class)) {
            Notification::make()->title(__('This slot cannot be taken'))->body($problem)->danger()->send();

            throw new Halt();
        }

        throw ValidationException::withMessages(['slot' => [$problem]]);
    }
}
