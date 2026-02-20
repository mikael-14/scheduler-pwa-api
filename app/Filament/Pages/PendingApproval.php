<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PendingApproval extends Page
{
    protected string $view = 'filament.pages.pending-approval';

    protected static bool $shouldRegisterNavigation = false;
}
