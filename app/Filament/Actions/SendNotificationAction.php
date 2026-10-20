<?php

namespace App\Filament\Actions;

use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\CustomNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification as Notifier;
use Spatie\Permission\Models\Role;

/**
 * An announcement written by an admin (e.g. "The shelter is closed on Monday") sent to everyone,
 * to the people with some roles, or to chosen people. It is an ordinary notification
 * (CustomNotification): bell, app list, push and email, and it shows up in the log.
 */
class SendNotificationAction
{
    public const EVERYONE = 'everyone';
    public const ROLES = 'roles';
    public const USERS = 'users';

    public static function make(): Action
    {
        return Action::make('send')
            ->label(__('New notification'))
            ->icon('heroicon-o-paper-airplane')
            ->modalHeading(__('New notification'))
            ->modalDescription(__('Sent in the app to every recipient, and by email and push to those who have them on.'))
            ->modalSubmitActionLabel(__('Send'))
            ->modalWidth('2xl')
            ->schema([
                Radio::make('audience')
                    ->label(__('Send to'))
                    ->options([
                        self::EVERYONE => __('Everyone'),
                        self::ROLES => __('People with a role'),
                        self::USERS => __('Specific people'),
                    ])
                    ->default(self::EVERYONE)
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('roles')
                    ->label(__('Roles'))
                    ->multiple()
                    ->options(fn() => Role::query()->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn(Get $get) => $get('audience') === self::ROLES)
                    ->required(fn(Get $get) => $get('audience') === self::ROLES)
                    ->live(),
                Select::make('users')
                    ->label(__('People'))
                    ->multiple()
                    ->searchable()
                    ->options(fn() => self::activeUsers()->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn(Get $get) => $get('audience') === self::USERS)
                    ->required(fn(Get $get) => $get('audience') === self::USERS)
                    ->live(),
                TextInput::make('subject')
                    ->label(__('Subject'))
                    ->required()
                    ->maxLength(150),
                Textarea::make('message')
                    ->label(__('Message'))
                    ->required()
                    ->rows(5)
                    ->maxLength(2000),
                ToggleButtons::make('level')
                    ->label(__('Type'))
                    ->options([
                        'info' => __('Information'),
                        'success' => __('Good news'),
                        'warning' => __('Warning'),
                        'danger' => __('Urgent'),
                    ])
                    ->colors(['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'])
                    ->icons([
                        'info' => 'heroicon-m-information-circle',
                        'success' => 'heroicon-m-check-circle',
                        'warning' => 'heroicon-m-exclamation-triangle',
                        'danger' => 'heroicon-m-bell-alert',
                    ])
                    ->default('info')
                    ->inline()
                    ->required(),
                Toggle::make('email_everyone')
                    ->label(__('Important: email everyone'))
                    ->helperText(__('Also emails the people who turned email notifications off.')),
                TextEntry::make('recipient_count')
                    ->hiddenLabel()
                    ->state(fn(Get $get) => __(':count people will receive it.', [
                        'count' => self::recipients($get('audience'), (array) $get('roles'), (array) $get('users'))->count(),
                    ]))
                    ->color('gray'),
            ])
            ->action(function (array $data) {
                $recipients = self::recipients($data['audience'], $data['roles'] ?? [], $data['users'] ?? [])->get();

                if ($recipients->isEmpty()) {
                    Notification::make()->title(__('Nobody matches the chosen recipients.'))->warning()->send();

                    return;
                }

                Notifier::send($recipients, new CustomNotification(
                    subject: $data['subject'],
                    message: $data['message'],
                    level: $data['level'],
                    emailEveryone: (bool) ($data['email_everyone'] ?? false),
                ));

                Notification::make()
                    ->title(__('Notification sent'))
                    ->body(__(':count people will receive it.', ['count' => $recipients->count()]))
                    ->success()
                    ->send();
            })
            ->visible(fn(): bool => (bool) auth()->user()?->can('send', NotificationLog::class));
    }

    /** Only people who can actually use the app: active and approved. */
    private static function activeUsers(): Builder
    {
        return User::query()->where('status', true)->whereNotNull('approved_at');
    }

    public static function recipients(?string $audience, array $roleIds = [], array $userIds = []): Builder
    {
        return match ($audience) {
            self::ROLES => $roleIds
                ? self::activeUsers()->whereHas('roles', fn(Builder $query) => $query->whereKey($roleIds))
                : self::activeUsers()->whereRaw('1 = 0'),
            self::USERS => self::activeUsers()->whereKey($userIds),
            default => self::activeUsers(),
        };
    }
}
