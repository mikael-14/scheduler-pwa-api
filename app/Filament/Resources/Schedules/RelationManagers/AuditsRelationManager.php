<?php

namespace App\Filament\Resources\ScheduleResource\RelationManagers;

use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($pageClass === \App\Filament\Resources\Schedules\Pages\ViewSchedule::class) {
            return auth()->user()->can('view_any_audit');
        }
        // Example 1: Using Spatie Permissions / Laravel Gates
        return false;
    }
    // 1. Infolist view structure (Triggers natively inside the modal/slide-over)
    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        // Custom calculation for parent/child mapping
                        TextEntry::make('resource_details')
                            ->label(__('Resource Details'))
                            ->weight('bold')
                            ->color('primary')
                            ->state(function ($record) {
                                if ($record->auditable_type === ScheduleUser::class) {
                                    $scheduleId = $record->auditable?->schedule_id ?? 'Unknown';
                                    return "User Row #{$record->auditable_id} (On Schedule #{$scheduleId})";
                                }
                                return class_basename($record->auditable_type) . " #{$record->auditable_id}";
                            }),

                        TextEntry::make('event')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('user.name')
                            ->label(__('Action By')),
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime(),
                        // Displays JSON differences cleanly as interactive key-value structures
                        KeyValueEntry::make('old_values')
                            ->label(__('Old Values'))
                            ->columnSpanFull(),
                        KeyValueEntry::make('new_values')
                            ->label(__('New Values'))
                            ->columnSpanFull(),
                    ])
            ]);
    }

    // 2. Table structure displaying combined timeline events
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->where(function ($q) {
                        $q->where('auditable_type', Schedule::class)
                            ->where('auditable_id', $this->getOwnerRecord()->id);
                    })
                    ->orWhere(function ($q) {
                        $q->where('auditable_type', ScheduleUser::class)
                            ->whereIn('auditable_id', ScheduleUser::where('schedule_id', $this->getOwnerRecord()->id)->pluck('id'));
                    })
            )
            ->columns([
                TextColumn::make('resource_details')
                    ->label(__('Resource Details'))
                    ->weight('bold')
                    ->getStateUsing(function ($record) {
                        if ($record->auditable_type === ScheduleUser::class) {
                            return "#{$record->auditable_id} " . User::where('id', $record->auditable?->user_id)->value('name');
                        }
                        return class_basename($record->auditable_type) . " #{$record->auditable_id}";
                    }),
                TextColumn::make('event')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label(__('Action By')),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('Visualizar Audit Log'))
                    ->slideOver(), // Swaps standard modal out for a modern Filament slide-over panel
            ]);
    }
}
