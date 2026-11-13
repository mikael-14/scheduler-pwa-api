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
use App\Models\ScheduleType;
use App\Models\ScheduleTypeSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Models\Audit;

/**
 * Who changed a schedule type, and what: from the admin panel or the PWA (both are audited,
 * see config/audit.php guards). A deleted type's last values stay in the audit table.
 * Its slots' changes are listed with it (a removed slot is found through its own audits).
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

    /** "Schedule Type" or "Slot 21/11/2026 09:00–11:00". */
    private static function subject($audit): string
    {
        return $audit->auditable_type === ScheduleTypeSlot::class
            ? ScheduleTypeSlot::auditLabel($audit)
            : __('Schedule Type');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('subject')
                            ->label(__('Subject'))
                            ->weight('bold')
                            ->state(fn($record) => self::subject($record))
                            ->columnSpanFull(),
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
        $typeId = $this->getOwnerRecord()->getKey();

        return $table
            // its own query: the type's audits and its slots', grouped (search and sort can't unbalance the OR)
            ->query(fn(): Builder => Audit::query()->where(fn(Builder $q) => $q
                ->where(fn(Builder $q) => $q
                    ->where('auditable_type', ScheduleType::class)
                    ->where('auditable_id', $typeId))
                ->orWhere(fn(Builder $q) => $q
                    ->where('auditable_type', ScheduleTypeSlot::class)
                    ->whereIn('auditable_id', ScheduleTypeSlot::everOf($typeId)))))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->weight('bold')
                    ->state(fn($record) => self::subject($record)),
                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn(string $state): string => self::eventColor($state)),
                TextColumn::make('user.name')
                    ->label(__('Action By'))
                    ->placeholder('—'),
                \App\Filament\Columns\AuditTagsColumn::make(),
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
