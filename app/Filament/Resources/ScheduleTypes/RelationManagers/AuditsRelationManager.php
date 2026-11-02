<?php

namespace App\Filament\Resources\ScheduleTypes\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Who changed a schedule type, and what: from the admin panel or the PWA (both are audited,
 * see config/audit.php guards). A deleted type's last values stay in the audit table.
 */
class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('History');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === \App\Filament\Resources\ScheduleTypes\Pages\ViewScheduleType::class
            && auth()->user()->can('view_any_audit');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('event')
                            ->label(__('Event'))
                            ->badge()
                            ->color(fn(string $state): string => self::eventColor($state)),
                        TextEntry::make('user.name')
                            ->label(__('Action By'))
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime(config('app.date_time_format')),
                        TextEntry::make('ip_address')
                            ->label(__('IP Address'))
                            ->placeholder('—'),
                        KeyValueEntry::make('old_values')
                            ->label(__('Old Values'))
                            ->columnSpanFull(),
                        KeyValueEntry::make('new_values')
                            ->label(__('New Values'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn(string $state): string => self::eventColor($state)),
                TextColumn::make('user.name')
                    ->label(__('Action By'))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('View Audit Log'))
                    ->slideOver(),
            ]);
    }

    private static function eventColor(string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            default => 'gray',
        };
    }
}
