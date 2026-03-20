<?php

namespace App\Filament\Resources\ScheduleTypes;

use App\Filament\Resources\ScheduleTypes\Pages\ManageScheduleTypes;
use App\Models\ScheduleType;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ScheduleTypeResource extends Resource
{
    protected static ?string $model = ScheduleType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('color')
                    ->default('#cccccc'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('color')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action) {
                        $record = $action->getRecord();
                        if ($record->schedules()->count() > 0) {
                            Notification::make()
                                ->title('Cannot delete record')
                                ->body('This record has related schedules and cannot be deleted.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $recordsWithoutSchedules = $records->filter(function (Model $record) {
                                return $record->schedules()->count() == 0;
                            });
                            if ($recordsWithoutSchedules->count() != $records->count()) {
                                Notification::make()
                                    ->title('Cannot delete some records')
                                    ->body('One or more selected records have related schedules and cannot be deleted.')
                                    ->danger()
                                    ->send();
                            }
                            $recordsWithoutSchedules->each->delete();
                        })
                        ->successNotificationTitle('Deleted')
                        ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                            if ($successCount) {
                                return "{$successCount} of {$totalCount} records deleted";
                            }

                            return 'Failed to delete any records';
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScheduleTypes::route('/'),
        ];
    }
}
