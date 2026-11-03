<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\CustomImpersonateAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\ModelHasRole;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignment;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
            DeleteAction::make()
                ->before(function ($record, $action) {
                    if ($record->schedules()->exists()) {
                        Notification::make()
                            ->title(__('Cannot delete user'))
                            ->body(__('This user has schedules and cannot be deleted.'))
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = ModelHasRole::where('model_id', $data['id'])->pluck('role_id')->toArray();
        $data['approved'] = isset($data['approved_at']) ? 1 : 0;
        return $data;
    }

    /** Active / building key: who edits other people (`update_any_user`), never on their own record. */
    public static function managesAccount(?User $record): bool
    {
        $me = Filament::auth()->user();

        return $me && $record && ! $record->is($me) && $me->can('update_any_user');
    }

    /** Approved: also `approve_user`, the same as the Approve action. */
    public static function mayApprove(?User $record): bool
    {
        return self::managesAccount($record) && Filament::auth()->user()->can('approve_user');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // only when the toggle was saved (see mayApprove); keeps the original approval date
        if (array_key_exists('approved', $data)) {
            $data['approved_at'] = $data['approved'] ? ($this->record->approved_at ?? now()) : null;
        }
        unset($data['approved']);

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
                                    ->label(__('Avatar'))
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
                                Grid::make()
                                    ->columns(3)
                                    ->schema([
                                        // the account itself: only by who manages others, never on one's own;
                                        // disabled ones are not saved, whatever the browser sends
                                        Toggle::make('status')
                                            ->label(__('Active'))
                                            ->inline(false)
                                            ->helperText(__('Admin panel access'))
                                            ->disabled(fn($record) => ! self::managesAccount($record))
                                            ->dehydrated(fn($record) => self::managesAccount($record)),
                                        Toggle::make('approved')
                                            ->label(__('Approved'))
                                            ->inline(false)
                                            ->helperText(fn($record) => __('Account approved') . ' ' . $record->approved_at)
                                            ->disabled(fn($record) => ! self::mayApprove($record))
                                            ->dehydrated(fn($record) => self::mayApprove($record)),
                                        Toggle::make('building_key')
                                            ->label(__('Building Key'))
                                            ->inline(false)
                                            ->helperText(__('User has a building key'))
                                            ->disabled(fn($record) => ! self::managesAccount($record))
                                            ->dehydrated(fn($record) => self::managesAccount($record)),
                                    ]),
                                Grid::make()
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('email_notification')
                                            ->label(__('Email notifications'))
                                            ->inline(false)
                                            ->helperText(__('Receive an email when a schedule is approved, rejected or cancelled.')),
                                        Toggle::make('push_notification')
                                            ->label(__('Push notifications'))
                                            ->inline(false)
                                            ->helperText(__('Receive push notifications on the devices where you enabled them in the app.')),
                                        Toggle::make('all_email_notification')
                                            ->label(__('All notifications by email'))
                                            ->inline(false)
                                            ->helperText(__('Also other people\'s schedules.')),
                                        Toggle::make('all_push_notification')
                                            ->label(__('All notifications by push'))
                                            ->inline(false)
                                            ->helperText(__('Also other people\'s schedules.')),
                                    ]),
                                // shown to who may see or give roles; changed only as RoleAssignment allows
                                // (never one's own, super admin only by a super admin)
                                Select::make('role')
                                    ->label(__('Roles'))
                                    ->options(fn($record) => RoleAssignment::canAssign(auth()->user(), $record)
                                        ? RoleAssignment::options(auth()->user())
                                        : Role::query()->pluck('name', 'id')->all())
                                    ->multiple()
                                    ->visible(fn() => auth()->user()->can('view_any_role') || auth()->user()->can(RoleAssignment::PERMISSION))
                                    ->disabled(fn($record) => ! RoleAssignment::canAssign(auth()->user(), $record))
                                    ->dehydrated(fn($record) => RoleAssignment::canAssign(auth()->user(), $record)),
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
        // roles: exactly the chosen ones (unticked ones removed), when this person may change them
        if (array_key_exists('role', $state)) {
            RoleAssignment::sync(Filament::auth()->user(), $this->record, $state['role'] ?? []);
        }
        $state['role'] = $this->record->roles()->pluck('id')->all();
        $this->form->fill($state);
    }
}
