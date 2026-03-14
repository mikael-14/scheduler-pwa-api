<?php

namespace App\Filament\Resources\Users\Schemas;

use Dom\Text;
use Filament\Infolists\Components\IconEntry;
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
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('locale'),
                IconEntry::make('status')
                    ->label('Active')
                    ->boolean(),
                TextEntry::make('approved_at')
                    ->dateTime(config('app.datetime_format'))
                    ->icon(Heroicon::CheckBadge)
                    ->color('success')
                    ->placeholder(__('Not approved')),
                TextEntry::make('created_at')
                    ->dateTime(config('app.datetime_format')),
                TextEntry::make('updated_at')
                    ->dateTime(config('app.datetime_format')),
                    ])
                    ->columns(2)
                    ->columnSpan('full'),
            ]);
    }
}
