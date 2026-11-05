<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tapp\FilamentAuditing\Models\Audit;

/**
 * The changes made to this user: profile, account switches and roles (the `roles` event).
 * Same layout as the schedule's audits; the password is never in them (User::$auditExclude).
 */
class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    /** On the user's View and Edit pages, only for who may see the audits. Read-only. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return in_array($pageClass, [ViewUser::class, EditUser::class], true)
            && (bool) Filament::auth()->user()?->can('viewAny', Audit::class);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Audits');
    }

    private static function eventColor(string $state): string
    {
        return match ($state) {
            'created' => 'success',
            'updated', 'roles' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            default => 'gray',
        };
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('event')
                            ->badge()
                            ->label(__('Event'))
                            ->color(fn(string $state): string => self::eventColor($state)),
                        TextEntry::make('user.name')
                            ->label(__('Action By')),
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime(),
                        TextEntry::make('ip_address')
                            ->label(__('IP Address')),
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
            // the Tapp model, so the View action goes through AuditPolicy (view_audit)
            ->query(fn(): Builder => Audit::query()
                ->where('auditable_type', $this->getOwnerRecord()->getMorphClass())
                ->where('auditable_id', $this->getOwnerRecord()->getKey()))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn(string $state): string => self::eventColor($state)),
                TextColumn::make('user.name')
                    ->label(__('Action By'))
                    ->placeholder(__('System')),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('View Audit Log'))
                    ->slideOver(),
            ]);
    }
}
