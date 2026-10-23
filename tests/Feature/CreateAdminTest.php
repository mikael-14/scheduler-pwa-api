<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_gets_an_approved_super_admin_with_every_permission(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Ana', '--email' => 'ana@example.pt', '--password' => 'secret123'])
            ->assertSuccessful();

        $user = User::where('email', 'ana@example.pt')->first();
        $this->assertTrue($user->status);
        $this->assertNotNull($user->approved_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue($user->can('approve_schedule'));
        $this->assertTrue($user->can('all_day_schedule'));

        // again: same account, new password
        $this->artisan('app:create-admin', ['--name' => 'Ana', '--email' => 'ana@example.pt', '--password' => 'another123'])
            ->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('another123', $user->fresh()->password));
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Ana', '--email' => 'ana@example.pt', '--password' => 'short'])
            ->assertFailed();
        $this->assertSame(0, User::count());
    }
}
