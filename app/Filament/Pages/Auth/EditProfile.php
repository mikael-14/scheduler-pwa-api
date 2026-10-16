<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Filament's own profile page, plus the user's language and notification preferences.
 * The same columns are edited from the PWA (PATCH /api/user).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Select::make('locale')
                    ->label(__('Language'))
                    ->options(collect(config('app-locales.available'))->pluck('name', 'code')->all())
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->required(),
                Section::make(__('Notifications'))
                    ->description(__('Choose how you want to be told about your schedules and your account.'))
                    ->schema([
                        Toggle::make('email_notification')
                            ->label(__('Email notifications'))
                            ->helperText(__('Receive an email when a schedule is approved, rejected or cancelled.')),
                        Toggle::make('push_notification')
                            ->label(__('Push notifications'))
                            ->helperText(__('Receive push notifications on the devices where you enabled them in the app.')),
                    ]),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    /** Reload after saving so a changed language applies to the page straight away. */
    protected function getRedirectUrl(): ?string
    {
        return Filament::getProfileUrl();
    }
}
