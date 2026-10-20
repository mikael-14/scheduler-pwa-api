<?php

namespace App\Filament\Resources\NotificationLogs\Schemas;

use App\Models\NotificationLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $format = config('app.date_time_format');

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Notification'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('recipient.name')
                            ->label(__('Recipient'))
                            ->helperText(fn(NotificationLog $record) => $record->recipient?->email),
                        TextEntry::make('sender.name')
                            ->label(__('Sent by'))
                            ->placeholder(__('System')),
                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->formatStateUsing(fn(NotificationLog $record) => $record->typeLabel())
                            ->helperText(fn(NotificationLog $record) => $record->type),
                        TextEntry::make('created_at')
                            ->label(__('Date'))
                            ->dateTime($format),
                        TextEntry::make('data.title')
                            ->label(__('Subject'))
                            ->columnSpanFull(),
                        TextEntry::make('data.body')
                            ->label(__('Message'))
                            ->formatStateUsing(fn($state) => strip_tags((string) $state))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('data.path')
                            ->label(__('Link in the app'))
                            ->placeholder('—'),
                        TextEntry::make('read_at')
                            ->label(__('Read at'))
                            ->dateTime($format)
                            ->placeholder(__('Not read')),
                    ]),
                Section::make(__('Delivery'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('in_app')
                            ->label(__('In the app'))
                            ->state(__('Sent'))
                            ->badge()
                            ->color('success')
                            ->helperText(__('Admin panel bell and the app\'s notification list.'))
                            ->columnSpanFull(),
                        TextEntry::make('mail_status')
                            ->label(__('Email'))
                            ->badge()
                            ->placeholder(__('Not sent')),
                        TextEntry::make('mail_sent_at')
                            ->label(__('Email sent at'))
                            ->dateTime($format)
                            ->placeholder('—'),
                        TextEntry::make('push_status')
                            ->label(__('Push'))
                            ->badge()
                            ->placeholder(__('Not sent')),
                        TextEntry::make('push_sent_at')
                            ->label(__('Push sent at'))
                            ->dateTime($format)
                            ->placeholder('—'),
                        TextEntry::make('delivery_error')
                            ->label(__('Delivery error'))
                            ->color('danger')
                            ->visible(fn(NotificationLog $record) => filled($record->delivery_error))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Stored data'))
                    ->collapsed()
                    ->schema([
                        TextEntry::make('raw_data')
                            ->hiddenLabel()
                            ->state(fn(NotificationLog $record) => json_encode($record->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                            ->fontFamily('mono')
                            ->extraAttributes(['class' => 'whitespace-pre-wrap break-all']),
                    ]),
            ]);
    }
}
