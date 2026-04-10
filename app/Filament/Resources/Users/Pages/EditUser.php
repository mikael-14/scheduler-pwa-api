<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\CustomImpersonateAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\ModelHasRole;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public static string | Alignment $formActionsAlignment = Alignment::End;


    protected function getHeaderActions(): array
    {
        return [
            CustomImpersonateAction::make('impersonate'),
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = ModelHasRole::where('model_id', $data['id'])->pluck('role_id')->toArray();
        return $data;
    }

    public function form(Schema $form): Schema
    {
      return $form
    ->schema([
        Grid::make()
            ->columns(3) // Set a 3-column grid to allow 2/3 and 1/3 split
            ->schema([
                Section::make()
                    ->heading(__('User data'))
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label('Avatar')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->imageEditor(),
                        TextInput::make('name')
                            ->label(__('Username'))
                            ->required(),
                        TextInput::make('email')
                            ->translateLabel()
                            ->placeholder('email@example.com')
                            ->helperText(__('Make sure this email is valid and unique'))
                            ->required()
                            ->unique(table: User::class, column: 'email', ignorable: fn($record) => $record, ignoreRecord: true),
                        Select::make('locale')
                            ->options(collect(config('app-locales.available'))->pluck('name', 'code')->toArray())
                            ->translateLabel()
                            ->selectablePlaceholder(false),
                        Toggle::make('status')
                            ->label(__('Active'))
                            ->inline(false)
                            ->helperText(__('Admin panel access')),
                        Select::make('role')
                            ->options(Role::all()->pluck('name', 'id')->toArray())
                            ->multiple()
                            ->hidden(!auth()->user()->can('ViewAny:Role'))
                            ->dehydrated(auth()->user()->can('ViewAny:Role')),
                    ])
                    // Logic: If same user, take 2 columns. If different, take all 3.
                    ->columnSpan(fn($record): int => ($record && auth()->id() === $record->id) ? 2 : 3),

                Section::make()
                    ->heading(__('Change password'))
                    ->description(__('Fill this in case you want to change password'))
                    ->schema([
                        TextInput::make('password')
                            ->translateLabel()
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->same('confirm_password'),
                        TextInput::make('confirm_password')
                            ->translateLabel()
                            ->dehydrated(false)
                            ->password(),
                    ])
                    ->columnSpan(1) // Password takes the remaining 1 column
                    ->visible(fn($record): bool => $record && auth()->id() === $record->id),
            ])
            ->columnSpanFull()
    ]);
    }
    // protected function getRedirectUrl(): string
    // {
    //     //don't know how to fix this (livewire component) field refresh 
    //     //for now let's refresh the page 
    //     return request()->header('Referer');
    // }

    protected function afterSave(): void
    {
        $state = $this->form->getState();
        //change password
        if (key_exists('password', $state) && Filament::auth()->user()->id == $this->record->id) {
            session()->forget('password_hash_' . config('filament.auth.guard'));
            Filament::auth()->login(Filament::auth()->user());
            $state['password'] = '';
            $state['confirm_password'] = '';
        }
        //change role
        if (isset($state['role'])) {
            foreach ($state['role'] as $roleId) {
                $this->record->assignRole($roleId);
            }
        } else {
            $this->record->roles()->detach();
        }
        $this->form->fill($state);
    }
}
