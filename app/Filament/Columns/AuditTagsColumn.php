<?php

namespace App\Filament\Columns;

use Filament\Tables\Columns\TextColumn;

/**
 * An audit's tags (audits.tags, comma separated, see App\Services\AuditTags) as badges: where the
 * change came from (admin, pwa, system) in its own colour, then the rest. Searchable.
 */
class AuditTagsColumn extends TextColumn
{
    public static function getDefaultName(): ?string
    {
        return 'tags';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Tags'))
            ->badge()
            ->separator(',')
            ->color(fn(?string $state): string => match ($state) {
                'admin' => 'primary',
                'pwa' => 'info',
                'system' => 'gray',
                'approved', 'activation' => 'success',
                'unapproved', 'password', 'roles', 'permissions' => 'warning',
                default => 'gray',
            })
            ->placeholder('—')
            ->searchable()
            ->toggleable()
            ->wrap();
    }
}
