<?php

namespace App\Filament\Resources\NotificationLogs;

use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Filament\Resources\NotificationLogs\Pages\ViewNotificationLog;
use App\Filament\Resources\NotificationLogs\Schemas\NotificationLogInfolist;
use App\Filament\Resources\NotificationLogs\Tables\NotificationLogsTable;
use App\Models\NotificationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only log of every notification stored for a user: who got it, who caused it, what it said
 * and whether its email and push copies went out.
 */
class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static ?string $slug = 'notifications';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('Notifications');
    }

    public static function getModelLabel(): string
    {
        return __('Notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Notifications');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['recipient', 'sender']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NotificationLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationLogs::route('/'),
            'view' => ViewNotificationLog::route('/{record}'),
        ];
    }
}
