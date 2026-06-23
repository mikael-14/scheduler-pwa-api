<?php 

namespace App\Filament\Columns;

use Filament\Tables\Columns\TextColumn;
use App\Models\Schedule;
use App\Models\ScheduleUser;

class AuditContextColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        // This dynamically evaluates what to print in the table column
        $this->getStateUsing(function ($record) {
            if (!$record->auditable_type) {
                return '-';
            }

            switch ($record->auditable_type) {
                case ScheduleUser::class:
                    // Fetch the parent schedule ID dynamically from the relation
                    $scheduleId = $record->auditable?->schedule_id ?? 'Unknown';
                    return "User Row #{$record->auditable_id} (On Schedule #{$scheduleId})";

                case Schedule::class:
                    return "Schedule #{$record->auditable_id}";

                default:
                    // Fallback for any other audited models (e.g., User)
                    $shortName = class_basename($record->auditable_type);
                    return "{$shortName} #{$record->auditable_id}";
            }
        });
    }
}