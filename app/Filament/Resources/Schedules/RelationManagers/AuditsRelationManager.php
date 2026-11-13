<?php

namespace App\Filament\Resources\Schedules\RelationManagers;

use App\Models\Schedule;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Tapp\FilamentAuditing\Models\Audit;
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

    /**
     * On the schedule's View and Edit pages, only for who may see the audits. Read-only on both:
     * the table has no create/edit/delete actions, only View.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return in_array($pageClass, [
            \App\Filament\Resources\Schedules\Pages\ViewSchedule::class,
            \App\Filament\Resources\Schedules\Pages\EditSchedule::class,
        ], true)
            && (bool) Filament::auth()->user()?->can('viewAny', Audit::class);
    }

    /**
     * The schedule_users rows that ever belonged to this schedule. Who left is deleted from
     * schedule_users, so their rows are found through their own audits too (schedule_id is kept
     * in the values they were created and deleted with).
     */
    private function participantRowIds(): array
    {
        $scheduleId = $this->getOwnerRecord()->getKey();

        return ScheduleUser::withTrashed()->where('schedule_id', $scheduleId)->pluck('id')
            ->merge(Audit::query()
                ->where('auditable_type', ScheduleUser::class)
                ->where(fn(Builder $q) => $q
                    ->where('new_values->schedule_id', $scheduleId)
                    ->orWhere('old_values->schedule_id', $scheduleId))
                ->pluck('auditable_id'))
            ->unique()->values()->all();
    }

    /** The participant of a schedule_users audit, also when that row is gone. */
    private static function participantUserId(Audit $audit): ?int
    {
        return $audit->auditable?->user_id
            ?? $audit->old_values['user_id']
            ?? $audit->new_values['user_id']
            ?? null;
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
                                    $scheduleId = $record->auditable?->schedule_id
                                        ?? $record->old_values['schedule_id']
                                        ?? $record->new_values['schedule_id']
                                        ?? 'Unknown';
                                    return "User Row #{$record->auditable_id} (On Schedule #{$scheduleId})";
                                }
                                return class_basename($record->auditable_type) . " #{$record->auditable_id}";
                            }),

                        TextEntry::make('event')
                            ->badge()
                            ->label(__('Event'))
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
            // its own query (not the audits() relation plus an orWhere): the schedule's audits and its
            // participants', grouped, so the table's search and sort cannot unbalance the OR
            ->query(fn(): Builder => Audit::query()->where(fn(Builder $q) => $q
                ->where(fn(Builder $q) => $q
                    ->where('auditable_type', Schedule::class)
                    ->where('auditable_id', $this->getOwnerRecord()->getKey()))
                ->orWhere(fn(Builder $q) => $q
                    ->where('auditable_type', ScheduleUser::class)
                    ->whereIn('auditable_id', $this->participantRowIds()))))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('resource_details')
                    ->label(__('Resource Details'))
                    ->weight('bold')
                    ->getStateUsing(function ($record) {
                        if ($record->auditable_type === ScheduleUser::class) {
                            return  " #{$record->auditable_id} " . __("Schedule User") . " " . User::where('id', self::participantUserId($record))->value('name');
                        }
                        return "#{$record->auditable_id} " . __("Agenda");
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
                \App\Filament\Columns\AuditTagsColumn::make(),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('View Audit Log'))
                    ->slideOver(), // Swaps standard modal out for a modern Filament slide-over panel
            ]);
    }
}
