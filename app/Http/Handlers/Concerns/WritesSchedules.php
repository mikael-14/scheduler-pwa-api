<?php

namespace App\Http\Handlers\Concerns;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use App\Services\ScheduleAccess;

/**
 * Shared by the create / update / patch handlers.
 */
trait WritesSchedules
{
    /**
     * Validation rules for the schedule and its participants.
     * Participants are created through the schedule, so the schedule id is never client supplied.
     */
    protected function scheduleRules(bool $partial = false): array
    {
        $rules = Schedule::getValidationRules();

        $rules['schedule_users'] = 'nullable|array';
        foreach (ScheduleUser::getValidationRules() as $key => $rule) {
            if ($key === 'schedule_id') {
                continue;
            }
            $rules["schedule_users.*.$key"] = $rule;
        }
        // Participant status is optional: new participants start as pending
        $rules['schedule_users.*.status'] = ['nullable', \Illuminate\Validation\Rule::enum(ScheduleStatus::class)];

        if ($partial) {
            $rules = array_map(
                fn($rule) => is_array($rule) ? ['sometimes', ...$rule] : 'sometimes|' . $rule,
                $rules
            );
        }

        return $rules;
    }

    /**
     * Create or update the participants of a schedule.
     * Staff may set participant statuses; everyone else only adds pending participants.
     *
     * @param array<int, array<string, mixed>> $incoming
     */
    protected function syncParticipants(Schedule $schedule, array $incoming, User $actor, bool $removeMissing): void
    {
        $staff = ScheduleAccess::isStaff($actor);
        $kept = [];

        foreach ($incoming as $row) {
            $userId = (int) $row['user_id'];

            // The owner is never their own participant
            if ($userId === (int) $schedule->user_id || isset($kept[$userId])) {
                continue;
            }

            $existing = $schedule->schedule_users()->where('user_id', $userId)->first();

            $status = $existing?->status ?? ScheduleStatus::Pending;
            if ($staff && ! empty($row['status'])) {
                $status = ScheduleStatus::from($row['status']);
            }

            $record = $schedule->schedule_users()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'status' => $status,
                    'description' => $row['description'] ?? $existing?->description,
                ]
            );

            $kept[$userId] = $record->id;
        }

        if ($removeMissing) {
            $schedule->schedule_users()->whereNotIn('id', array_values($kept))->delete();
        }
    }

    protected function forbidden(string $message)
    {
        return response()->json(['message' => $message, 'code' => 'forbidden'], 403);
    }
}
