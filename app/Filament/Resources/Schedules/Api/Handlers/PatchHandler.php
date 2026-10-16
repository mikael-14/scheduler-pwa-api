<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use Rupadana\ApiService\Http\Handlers;

/**
 * Partial update: same rules as the PUT handler, but only the sent fields are validated and changed.
 */
class PatchHandler extends UpdateHandler
{
    protected bool $partial = true;

    public static function getMethod()
    {
        return Handlers::PATCH;
    }
}
