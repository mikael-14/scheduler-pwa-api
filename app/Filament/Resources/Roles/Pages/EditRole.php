<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Services\RoleManagement;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as ShieldEditRole;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Override;

/** Shield's page, saving only the permissions RoleManagement::allowed lets this person give. */
class EditRole extends ShieldEditRole
{
    protected static string $resource = RoleResource::class;

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        $chosen = $this->permissions;
        $this->permissions = RoleManagement::allowed(Filament::auth()->user(), $chosen, $data['guard_name'] ?? 'web');

        if ($chosen->count() !== $this->permissions->count()) {
            Notification::make()
                ->title(__('Some permissions were not saved'))
                ->body(__('You can only give permissions you have yourself.'))
                ->warning()
                ->send();
        }

        return $data;
    }
}
