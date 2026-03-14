<?php

namespace App\Filament\Actions;

use XliteDev\FilamentImpersonate\Actions\ImpersonateAction;

class CustomImpersonateAction extends ImpersonateAction
{
    protected function setUp(): void
    {
        parent::setUp();

        //Button styling
        //$this->button();

        $this->label('Impersonate');

    }
}