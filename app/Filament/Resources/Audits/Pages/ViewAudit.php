<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use Tapp\FilamentAuditing\Filament\Resources\Audits\Pages\ViewAudit as BaseViewAudit;

class ViewAudit extends BaseViewAudit
{
    protected static string $resource = AuditResource::class;
}
