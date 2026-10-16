<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Pages\ViewAudit;
use Tapp\FilamentAuditing\Filament\Resources\Audits\AuditResource as BaseAuditResource;

/**
 * The package resource with translated labels. Its navigation group and label cannot be set
 * through the package's static setters (they are shared by every resource), and the package
 * hard-codes English, so they are overridden here and registered in config/filament-auditing.php.
 */
class AuditResource extends BaseAuditResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('Audits');
    }

    public static function getModelLabel(): string
    {
        return __('Audit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Audits');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudits::route('/'),
            'view' => ViewAudit::route('/{record}'),
        ];
    }
}
