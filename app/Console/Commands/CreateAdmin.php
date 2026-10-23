<?php

namespace App\Console\Commands;

use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The first account of a fresh install: active, approved, email verified and super admin
 * (every permission). Asks for what is not given:
 *
 *   php artisan app:create-admin
 *   php artisan app:create-admin --name="Ana" --email=ana@example.pt
 *
 * The permissions are generated first when the database has none yet. Running it again for an
 * existing email updates that account instead (handy to reset a forgotten password).
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Full name}
        {--email= : Email used to sign in}
        {--password= : Password (asked without echo when left out)}';

    protected $description = 'Create (or update) an active, approved super admin account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (at least 8 characters)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! Utils::getPermissionModel()::query()->exists()) {
            $this->call('db:seed', ['--class' => PermissionsSeeder::class, '--force' => true]);
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => $password, // hashed by the model cast
            'status' => true,
            'approved_at' => $user->approved_at ?? now(),
        ]);
        $user->email_verified_at ??= now();
        $user->save();

        $role = Utils::createRole(); // super_admin, created when missing
        $role->syncPermissions(Utils::getPermissionModel()::pluck('id'));
        $user->assignRole($role);

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated') . " super admin {$email}.");

        return self::SUCCESS;
    }
}
