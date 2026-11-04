<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Services\RoleAssignment;
use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class CreateUser extends CreateRecord
{

    public static string | Alignment $formActionsAlignment = Alignment::End;

    protected static string $resource = UserResource::class;

    public function form(Schema $form): Schema
    {
        return UserForm::configure($form)
            ->schema([
                Section::make()
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label(__('Avatar'))
                            ->image()
                            ->avatar()
                            ->disk('public') // Explicitly use the public disk
                            ->directory('avatars')
                            ->visibility('public') // Ensure the file is saved with public visibility
                            ->imageEditor(),
                        TextInput::make('name')
                            ->label(__('Username'))
                            ->required(),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->placeholder('email@example.com')
                            ->helperText(__('Make sure this email is valid and unique'))
                            ->unique(table: User::class, column: 'email', ignoreRecord: true)
                            ->required(),
                        Select::make('locale')->options(
                            collect(config('app-locales.available'))
                                ->pluck('name', 'code')
                                ->toArray()
                        )->default('pt')
                            ->selectablePlaceholder(false)
                            ->native(false),
                        Select::make('role')
                            ->label(__('Roles'))
                            // only with assign_role_user; super admin only by a super admin (RoleAssignment)
                            ->options(fn() => RoleAssignment::options(Filament::auth()->user()))
                            ->multiple()
                            ->native(false)
                            ->visible(fn() => RoleAssignment::canAssign(Filament::auth()->user())),
                        Fieldset::make(__('Account'))
                            ->columns(3)
                            ->schema([
                                Toggle::make('approved')
                                    ->label(__('Approved'))
                                    ->inline(false)
                                    ->helperText(__('Approve account'))
                                    ->default(0)
                                    // approving needs approve_user, as the Approve action
                                    ->disabled(fn() => ! Filament::auth()->user()->can('approve_user'))
                                    ->dehydrated(fn() => Filament::auth()->user()->can('approve_user')),
                                Toggle::make('status')
                                    ->label(__('Active'))
                                    ->inline(false)
                                    ->helperText(__('Access'))
                                    ->default(1),
                                Toggle::make('building_key')
                                    ->label(__('Building Key'))
                                    ->inline(false)
                                    ->helperText(__('User has a building key'))
                                    ->default(0),
                            ]),
                        // one column per channel: their own schedules, then everyone's
                        Fieldset::make(__('Notifications'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('email_notification')
                                    ->label(__('Email notifications'))
                                    ->inline(false)
                                    ->default(true)
                                    ->helperText(__('Receive an email when a schedule is approved, rejected or cancelled.')),
                                Toggle::make('push_notification')
                                    ->label(__('Push notifications'))
                                    ->inline(false)
                                    ->default(true)
                                    ->helperText(__('Receive push notifications on the devices where you enabled them in the app.')),
                                Toggle::make('all_email_notification')
                                    ->label(__('All notifications by email'))
                                    ->inline(false)
                                    ->default(true)
                                    ->helperText(__('Also other people\'s schedules.')),
                                Toggle::make('all_push_notification')
                                    ->label(__('All notifications by push'))
                                    ->inline(false)
                                    ->default(true)
                                    ->helperText(__('Also other people\'s schedules.')),
                            ]),
                    ]),
                Section::make()
                    ->schema([
                        TextInput::make('password')
                            ->label(__('Define a password'))
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->same('confirm_password')
                            ->required(),
                        TextInput::make('confirm_password')
                            ->label(__('Confirm password'))
                            ->password()
                            ->dehydrated(false)
                            ->required(),
                    ]),
            ]);
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the "approved_at" field based on the "approved" toggle value
        // (absent when the creator may not approve: the account waits for approval)
        $data['approved_at'] = ! empty($data['approved']) ? now() : null;
        unset($data['approved']);

        // Update the form state with the modified values
        return $data;
    }

    protected function afterCreate(): void
    {
        // Runs after the form fields are saved to the database.
        //assign role
        $state = $this->form->getState();
        if (! empty($state['role'])) {
            RoleAssignment::sync(Filament::auth()->user(), $this->record, $state['role']);
        }
    }
}
