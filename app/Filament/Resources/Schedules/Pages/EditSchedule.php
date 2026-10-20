<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Resources\Schedules\ScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use App\Services\PrimaryScheduleRule;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSchedule extends EditRecord
{
    protected static string $resource = ScheduleResource::class;

    /** The participants in the form are saved after the schedule; the primary rule counts them already. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return PrimaryScheduleRule::withCompanions(
            collect($this->data['schedules'] ?? [])->pluck('user_id')->all(),
            fn() => parent::handleRecordUpdate($record, $data)
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
