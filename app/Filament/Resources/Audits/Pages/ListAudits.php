<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Filament\Resources\Audits\AuditResource;
use Tapp\FilamentAuditing\Filament\Resources\Audits\Pages\ListAudits as BaseListAudits;

class ListAudits extends BaseListAudits
{
    protected static string $resource = AuditResource::class;
}
