<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Notifications\AccountApproved;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Approving a user always requires choosing the role they will get.
 * One implementation shared by the row action and the bulk action.
 */
class ApproveUserAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon('heroicon-o-check')
            ->iconButton()
            ->color('success')
            ->modalHeading(fn(User $record) => __('Approve :name', ['name' => $record->name]))
            ->modalDescription(__('Choose the role this user will have after approval.'))
            // The select is "required" so the modal can never be submitted without a role
            ->modalSubmitActionLabel(__('Approve'))
            ->schema([self::roleSelect()
                ->default(fn(User $record) => $record->roles->first()?->getKey())])
            ->action(function (User $record, array $data) {
                self::approve($record, $data['role']);

                Notification::make()
                    ->title(__('User approved successfully'))
                    ->success()
                    ->send();
            })
            ->visible(fn(User $record): bool => is_null($record->approved_at) && self::canApprove());
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('approve')
            ->label(__('Approve selected'))
            ->icon('heroicon-o-check')
            ->color('success')
            ->modalHeading(__('Approve selected users'))
            ->modalDescription(__('The chosen role is assigned to every selected user that is not approved yet.'))
            ->modalSubmitActionLabel(__('Approve'))
            ->schema([self::roleSelect()])
            ->action(function (Collection $records, array $data) {
                $count = 0;

                $records->each(function (User $record) use ($data, &$count) {
                    if (is_null($record->approved_at)) {
                        self::approve($record, $data['role']);
                        $count++;
                    }
                });

                Notification::make()
                    ->title(__('Users approved successfully'))
                    ->body(__(':count user(s) approved.', ['count' => $count]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion()
            ->visible(fn(): bool => self::canApprove());
    }

    public static function approve(User $user, int|string $roleId): void
    {
        $role = self::roleModel()::findOrFail($roleId);

        DB::transaction(function () use ($user, $role) {
            $user->update(['approved_at' => now()]);
            $user->assignRole($role);
        });

        // In-app notification, plus email / push when the user has them enabled
        try {
            $user->notify(new AccountApproved());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected static function roleSelect(): Select
    {
        return Select::make('role')
            ->label(__('Role'))
            ->options(fn() => self::roleOptions())
            ->required()
            ->native(false)
            ->searchable()
            ->preload();
    }

    /**
     * Only super admins may hand out the super_admin role.
     *
     * @return array<int|string, string>
     */
    protected static function roleOptions(): array
    {
        $query = self::roleModel()::query()->orderBy('name');

        if (! Filament::auth()->user()?->hasRole('super_admin')) {
            $query->where('name', '!=', 'super_admin');
        }

        return $query->pluck('name', 'id')->toArray();
    }

    protected static function canApprove(): bool
    {
        return (bool) Filament::auth()->user()?->can('approve_user');
    }

    /** @return class-string<\Spatie\Permission\Models\Role> */
    protected static function roleModel(): string
    {
        return config('permission.models.role');
    }
}
