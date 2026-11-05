<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\ModelHasRole;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Tapp\FilamentAuditing\Models\Audit;

/**
 * What this user did: the audits they are the author of (User::performedAudits), whatever they
 * changed — schedules, participants, other users, roles, types. The changes made *to* them are in
 * AuditsRelationManager.
 */
class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'performedAudits';

    /** What each audited model is called in the list (and in the filter). */
    private static function kinds(): array
    {
        return [
            Schedule::class => __('Schedule'),
            ScheduleUser::class => __('Participant'),
            User::class => __('User'),
            ModelHasRole::class => __('Role'),
            Role::class => __('Role'),
            ScheduleType::class => __('Schedule Type'),
        ];
    }

    /** On the user's View and Edit pages, only for who may see the audits. Read-only. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return in_array($pageClass, [ViewUser::class, EditUser::class], true)
            && (bool) Filament::auth()->user()?->can('viewAny', Audit::class);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Activity');
    }

    /** A value of the audited row, also when that row is gone (then from the audit's values). */
    private static function value(Audit $audit, string $key): mixed
    {
        return $audit->auditable?->{$key}
            ?? $audit->old_values[$key]
            ?? $audit->new_values[$key]
            ?? null;
    }

    /** "Schedule #12", "Participant Maria · Schedule #12", "User Maria", "Role Admin"... */
    private static function subject(Audit $audit): string
    {
        $kind = self::kinds()[$audit->auditable_type] ?? class_basename($audit->auditable_type);

        return match ($audit->auditable_type) {
            Schedule::class => "{$kind} #{$audit->auditable_id}",
            ScheduleUser::class => trim($kind . ' ' . User::withTrashed()->whereKey(self::value($audit, 'user_id'))->value('name'))
                . ' · ' . __('Schedule') . ' #' . self::value($audit, 'schedule_id'),
            User::class => trim($kind . ' ' . (User::withTrashed()->whereKey($audit->auditable_id)->value('name') ?? "#{$audit->auditable_id}")),
            ModelHasRole::class => trim($kind . ' ' . Role::whereKey(self::value($audit, 'role_id'))->value('name')),
            Role::class, ScheduleType::class => trim($kind . ' ' . (self::value($audit, 'name') ?? "#{$audit->auditable_id}")),
            default => "{$kind} #{$audit->auditable_id}",
        };
    }

    /** A link to the schedule or user changed, when it still exists and may be opened. */
    private static function subjectUrl(Audit $audit): ?string
    {
        [$resource, $page, $id] = match ($audit->auditable_type) {
            Schedule::class => [ScheduleResource::class, 'infolist', $audit->auditable_id],
            ScheduleUser::class => [ScheduleResource::class, 'infolist', self::value($audit, 'schedule_id')],
            User::class => [UserResource::class, 'view', $audit->auditable_id],
            default => [null, null, null],
        };
        if (! $resource || ! $id) {
            return null;
        }
        $record = $resource::getEloquentQuery()->whereKey($id)->first();

        return $record && Gate::allows('view', $record) ? $resource::getUrl($page, ['record' => $record]) : null;
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
                        TextEntry::make('subject')
                            ->label(__('Subject'))
                            ->weight('bold')
                            ->state(fn(Audit $record) => self::subject($record))
                            ->url(fn(Audit $record) => self::subjectUrl($record)),
                        TextEntry::make('event')
                            ->label(__('Event'))
                            ->badge()
                            ->color(fn(string $state): string => self::eventColor($state)),
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
            ->modifyQueryUsing(fn($query) => $query->with('auditable'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->weight('bold')
                    ->state(fn(Audit $record) => self::subject($record))
                    ->url(fn(Audit $record) => self::subjectUrl($record)),
                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn(string $state): string => self::eventColor($state)),
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('auditable_type')
                    ->label(__('Type'))
                    ->options(self::kinds()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('View Audit Log'))
                    ->slideOver(),
            ]);
    }
}
