<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            ]);
    }
}
