<?php

namespace App\Filament\Resources\ScheduleTypes\Pages;

use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditScheduleType extends EditRecord
{
    protected static string $resource = ScheduleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // same rule as the table (and the API): not while the type has schedules
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if (! $this->getRecord()->canBeDeleted()) {
                        Notification::make()
                            ->title(__('Cannot delete record'))
                            ->body(__('This record has related schedules and cannot be deleted.'))
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
