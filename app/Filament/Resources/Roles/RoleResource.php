<?php

namespace App\Filament\Resources\Roles;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Override;

/**
 * Shield's Roles screen, with the app's rules (App\Services\RoleManagement, RolePolicy): its pages
 * save only permissions that exist and that the person saving has, and the bulk delete checks
 * each role as if alone. Being in app/Filament/Resources, Shield does not register its own.
 */
class RoleResource extends ShieldRoleResource
{
    #[Override]
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->toolbarActions([
                DeleteBulkAction::make()->authorizeIndividualRecords(),
            ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
