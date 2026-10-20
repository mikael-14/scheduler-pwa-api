<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Services\PrimaryScheduleRule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    /** The participants in the form are saved after the schedule; the primary rule counts them already. */
    protected function handleRecordCreation(array $data): Model
    {
        return PrimaryScheduleRule::withCompanions(
            collect($this->data['schedules'] ?? [])->pluck('user_id')->all(),
            fn() => parent::handleRecordCreation($data)
        );
    }
}
