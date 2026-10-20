<?php

namespace App\Filament\Resources\NotificationLogs\Tables;

use App\Enums\DeliveryStatus;
use App\Models\NotificationLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationLogsTable
{
    /** Value of the delivery filters for "not sent that way" (no status). */
    private const NOT_SENT = 'none';

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable(),
                TextColumn::make('recipient.name')
                    ->label(__('Recipient'))
                    ->description(fn(NotificationLog $record) => $record->recipient?->email)
                    ->searchable(),
                TextColumn::make('sender.name')
                    ->label(__('Sent by'))
                    ->placeholder(__('System'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn(NotificationLog $record) => $record->typeLabel())
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('data.title')
                    ->label(__('Subject'))
                    ->description(fn(NotificationLog $record) => strip_tags((string) ($record->data['body'] ?? '')))
                    ->wrap()
                    // title and body live in the JSON `data` column
                    ->searchable(query: fn(Builder $query, string $search) => $query->where('data', 'like', "%{$search}%")),
                TextColumn::make('mail_status')
                    ->label(__('Email'))
                    ->badge()
                    ->placeholder(__('Not sent'))
                    ->tooltip(fn(NotificationLog $record) => $record->mail_sent_at?->format(config('app.date_time_format'))),
                TextColumn::make('push_status')
                    ->label(__('Push'))
                    ->badge()
                    ->placeholder(__('Not sent'))
                    ->tooltip(fn(NotificationLog $record) => $record->push_sent_at?->format(config('app.date_time_format'))),
                IconColumn::make('read_at')
                    ->label(__('Read'))
                    ->boolean()
                    ->state(fn(NotificationLog $record) => $record->read_at !== null)
                    ->tooltip(fn(NotificationLog $record) => $record->read_at?->format(config('app.date_time_format'))),
                TextColumn::make('mail_sent_at')
                    ->label(__('Email sent at'))
                    ->dateTime(config('app.date_time_format'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('push_sent_at')
                    ->label(__('Push sent at'))
                    ->dateTime(config('app.date_time_format'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_error')
                    ->label(__('Delivery error'))
                    ->limit(60)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('recipient')
                    ->label(__('Recipient'))
                    ->relationship('recipient', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('sender')
                    ->label(__('Sent by'))
                    ->relationship('sender', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(NotificationLog::typeOptions()),
                self::deliveryFilter('mail_status', __('Email')),
                self::deliveryFilter('push_status', __('Push')),
                TernaryFilter::make('read_at')
                    ->label(__('Read'))
                    ->nullable(),
                Filter::make('created_at')
                    ->label(__('Date'))
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn(Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn(array $data) => array_filter([
                        ($data['from'] ?? null) ? __('From') . ' ' . $data['from'] : null,
                        ($data['until'] ?? null) ? __('Until') . ' ' . $data['until'] : null,
                    ])),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /** Sent / queued / failed, or "not sent" for rows where that way was not used. */
    private static function deliveryFilter(string $column, string $label): SelectFilter
    {
        $options = collect(DeliveryStatus::cases())
            ->mapWithKeys(fn(DeliveryStatus $status) => [$status->value => $status->getLabel()])
            ->put(self::NOT_SENT, __('Not sent'))
            ->all();

        return SelectFilter::make($column)
            ->label($label)
            ->options($options)
            ->query(fn(Builder $query, array $data) => match ($data['value'] ?? null) {
                null, '' => $query,
                self::NOT_SENT => $query->whereNull($column),
                default => $query->where($column, $data['value']),
            });
    }
}
