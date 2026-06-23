<?php

namespace App\Filament\Resources\Users\Schemas;

use Dom\Text;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User information')
                    ->schema([
                        ImageEntry::make('avatar_url')
                            ->imageHeight(100)
                            ->disk('public')
                            ->visibility('public')
                            ->circular(),
                        TextEntry::make('roles.name')
                            ->label(__('Assigned Roles'))
                            ->listWithLineBreaks()
                            ->badge()
                            ->visible(auth()->user()->can('view_any_role')),
                        TextEntry::make('name')
                            ->label(__('Username')),
                        TextEntry::make('email')
                            ->label(__('Email'))
                            ->placeholder('email@example.com'),
                        TextEntry::make('locale')
                            ->translateLabel(),
                        IconEntry::make('status')
                            ->label(__('Active'))
                            ->boolean(),
                        TextEntry::make('approved_at')
                            ->dateTime(config('app.date_time_format'))
                            ->label(__('Approved At'))
                            ->icon(Heroicon::CheckBadge)
                            ->color('success')
                            ->placeholder(__('Not approved')),
                        TextEntry::make('created_at')
                        ->label(__('Created At'))
                        ->translateLabel()
                            ->dateTime(config('app.date_time_format')),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(config('app.date_time_format')),
                    ])
                    ->columns(2)
                    ->columnSpan('full'),
            ]);
    }
}
