<?php

namespace App\Filament\Resources\ScheduleTypes;

use App\Filament\Resources\ScheduleTypes\Pages\CreateScheduleType;
use App\Filament\Resources\ScheduleTypes\Pages\EditScheduleType;
use App\Filament\Resources\ScheduleTypes\Pages\ListScheduleTypes;
use App\Filament\Resources\ScheduleTypes\Pages\ViewScheduleType;
use App\Filament\Resources\ScheduleTypes\Schemas\ScheduleTypeForm;
use App\Filament\Resources\ScheduleTypes\Schemas\ScheduleTypeInfolist;
use App\Filament\Resources\ScheduleTypes\Tables\ScheduleTypesTable;
use App\Models\ScheduleType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ScheduleTypeResource extends Resource
{
    protected static ?string $model = ScheduleType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog;
    
    protected static string|UnitEnum|null $navigationGroup = 'Scheduler';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ScheduleTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ScheduleTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduleTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduleTypes::route('/'),
            'create' => CreateScheduleType::route('/create'),
            'view' => ViewScheduleType::route('/{record}'),
            'edit' => EditScheduleType::route('/{record}/edit'),
        ];
    }
}
