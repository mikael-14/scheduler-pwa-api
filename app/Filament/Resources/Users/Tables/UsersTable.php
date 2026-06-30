<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Filament\Actions\CustomImpersonateAction;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Zvizvi\UserFields\Components\UserColumn;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                UserColumn::make('username')
                    ->label(__('User'))
                    ->state(fn(User $record) => $record)
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->color('primary'),
                IconColumn::make('status')
                    ->label(__('Active'))
                    ->sortable()
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('approved_at')
                    ->label(__('Approved At'))
                    ->dateTime(config('app.date_time_format'))
                    ->placeholder(__('Not approved'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('locale')
                    ->label(__('Locale'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Active'))
                    ->options([
                        '1' => __('Active'),
                        '0' => __('Inactive'),
                    ]),
                TernaryFilter::make('approved')
                    ->label(__('Approved'))
                    ->trueLabel(__('Approved'))
                    ->falseLabel(__('Not approved'))
                    ->queries(
                        true: fn($query) => $query->whereNotNull('approved_at'),
                        false: fn($query) => $query->whereNull('approved_at'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                CustomImpersonateAction::make('impersonate'),
                ViewAction::make()->iconButton(),
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label(__('Approve selected'))
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'approved_at' => now(),
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(__('Users approved successfully')),
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                if ($record->schedules()->exists()) {
                                    Notification::make()
                                        ->title(__('Cannot delete user'))
                                        ->body(__('This user has schedules and cannot be deleted.'))
                                        ->danger()
                                        ->send();
                                } else {
                                    $record->delete();
                                }
                            });
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(__('Records deleted successfully')),
                    RestoreBulkAction::make(),
                ]),
            ])->checkIfRecordIsSelectableUsing(
                fn(Model $record): bool => $record->id !== Auth::id(),
            );
    }
}
