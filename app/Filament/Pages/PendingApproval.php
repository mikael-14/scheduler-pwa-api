<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

// A panel page (so it routes behind auth) rendered with the same layout as SimplePage, like the login screen.
class PendingApproval extends Page
{
    protected string $view = 'filament-panels::pages.simple';

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        // Approved accounts have nothing to wait for
        if (filament()->auth()->user()?->approved_at !== null) {
            $this->redirect(filament()->getUrl());
        }
    }

    protected function getLayoutData(): array
    {
        return [
            // The logout button lives in the page content, so the user menu is not needed.
            'hasTopbar' => false,
        ];
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public function getTitle(): string | Htmlable
    {
        return __('Account Pending Approval');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return filament()->auth()->user()?->email;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make(__('Your account has been successfully created and is now awaiting approval from an administrator.')),
                Callout::make(__('Awaiting administrator approval'))
                    ->description(__('You will be notified once your account has been reviewed and approved.'))
                    ->icon(Heroicon::OutlinedClock)
                    ->warning(),
                Text::make(__('If you have any questions, please contact our support team.'))
                    ->color('gray'),
                Actions::make([
                    Action::make('logout')
                        ->label(__('filament-panels::layout.actions.logout.label'))
                        ->icon(Heroicon::ArrowLeftStartOnRectangle)
                        ->color('gray')
                        ->url(filament()->getLogoutUrl())
                        ->postToUrl(),
                ])->fullWidth(),
            ]);
    }
}
