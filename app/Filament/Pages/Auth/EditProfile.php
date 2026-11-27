<?php

namespace App\Filament\Pages\Auth;

use App\Http\Middleware\ApplyUserLocale;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Filament's own profile page, plus the user's photo, language and notification preferences.
 * The same columns are edited from the PWA (PATCH /api/user).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // same field as the user edit form (Users → Edit)
                FileUpload::make('avatar_url')
                    ->label(__('Avatar'))
                    ->image()
                    ->avatar()
                    ->disk('public')
                    ->directory('avatars')
                    ->visibility('public')
                    ->imageEditor(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Select::make('locale')
                    ->label(__('Language'))
                    ->helperText(__('Used for notifications and email, and when you sign in. The language menu at the top only changes this session.'))
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
                        // the emails that can wait (decisions about you and account emails always come right away)
                        Select::make('email_digest')
                            ->label(__('Email summary'))
                            ->options(\App\Enums\DigestFrequency::class)
                            ->helperText(__('News such as people joining, changes and requests waiting can come together in one email. Decisions about you and account emails always come right away.'))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->required(),
                    ]),
                Section::make(__('All notifications'))
                    ->description(__('Also hear about other people\'s schedules: booked, approved, changed, cancelled, and people joining or leaving.'))
                    ->schema([
                        Toggle::make('all_email_notification')
                            ->label(__('By email')),
                        Toggle::make('all_push_notification')
                            ->label(__('By push')),
                    ]),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    /** A new profile language also becomes this session's (over a switcher choice). */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $before = $record->locale;
        $record = parent::handleRecordUpdate($record, $data);

        if ($record->locale !== $before) {
            session()->forget(ApplyUserLocale::SESSION_CHOICE);
            session()->put('locale', $record->locale);
        }

        return $record;
    }

    /** Reload after saving so a changed language applies to the page straight away. */
    protected function getRedirectUrl(): ?string
    {
        return Filament::getProfileUrl();
    }
}
