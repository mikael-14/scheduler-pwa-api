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
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Username')
                    ->translateLabel()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                IconColumn::make('status')
                    ->label('Active')
                    ->sortable()
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->placeholder('Not approved')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('locale')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                TernaryFilter::make('approved')
                    ->label('Approved')
                    ->trueLabel('Approved')
                    ->falseLabel('Not Approved')
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
                        ->label('Approve selected')
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
                        ->successNotificationTitle('Users approved successfully'),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])->checkIfRecordIsSelectableUsing(
                fn(Model $record): bool => $record->id !== Auth::id(),
            );
    }
}
