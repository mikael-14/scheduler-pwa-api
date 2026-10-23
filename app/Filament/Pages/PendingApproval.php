<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PendingApproval extends Page
{
    protected string $view = 'filament.pages.pending-approval';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string | Htmlable
    {
        return __('Account Pending Approval');
    }

    public static function getNavigationLabel(): string
    {
        return __('Account Pending Approval');
    }

    // The card in the view carries the heading, so skip the default page header.
    public function getHeading(): string | Htmlable | null
    {
        return null;
    }
}
