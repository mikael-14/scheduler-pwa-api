<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Form;
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
                        TextInput::make('name')
                            ->label(__('Username'))
                            ->required(),
                        TextInput::make('email')
                            ->translateLabel()
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
                            ->translateLabel()
                            ->options(
                                Role::all()->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->multiple()
                            ->native(false)
                            ->visible(Filament::auth()->user()->can('ViewAny:Role')),
                        Toggle::make('status')
                            ->inline(false)
                            ->helperText(__('Access'))
                            ->default(1),
                        Toggle::make('aproved')
                            ->inline(false)
                            ->helperText(__('Aprove account'))
                            ->default(0),
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
        $data['approved_at'] = $data['aproved'] ? now() : null;

        // Update the form state with the modified values
        return $data;
    }
    protected function afterCreate(): void
    {
        // Runs after the form fields are saved to the database.
        //assign role
        $state = $this->form->getState();
        if (isset($state['role'])) {
            foreach ($state['role'] as $roleId) {
                $this->record->assignRole($roleId);
            }
        }
    }
}
