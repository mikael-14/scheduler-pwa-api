<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\CustomImpersonateAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            CustomImpersonateAction::make('impersonate')
            ->hidden(
                function ($record): bool {
                    if ($record->id == auth()->id())
                    {
                        return true;
                    }
                    return false;
                }
            ), 
        ];
    }
}
