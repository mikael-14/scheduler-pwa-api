<?php

namespace Tests\Feature;

use App\Models\SocialiteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * The admin panel's Google / Facebook login (filament-socialite plugin, session based).
 * Each test runs in its own process because Filament keeps panel state in statics.
 */
class AdminSocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK = '/admin/oauth/callback/google';

    private function googleReturns(array $overrides = []): void
    {
        $oauthUser = (new ProviderUser)->setRaw($overrides['raw'] ?? ['email_verified' => true])->map([
            'id' => $overrides['id'] ?? 'g-1',
            'name' => 'Social Person',
            'email' => array_key_exists('email', $overrides) ? $overrides['email'] : 'social@example.com',
            'avatar' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($oauthUser);

        $factory = Mockery::mock();
        $factory->shouldReceive('driver')->andReturn($driver);
        Socialite::swap($factory);
    }

    private function assertRefused(string $message): void
    {
        $this->get(self::CALLBACK)
            ->assertRedirect('/admin/login')
            ->assertSessionHas('filament-socialite-login-error', $message);
        $this->assertGuest();
    }

    #[RunInSeparateProcess]
    public function test_new_google_user_is_registered_linked_and_sent_to_the_pending_page(): void
    {
        $this->withoutVite();
        $this->googleReturns();

        $this->get(self::CALLBACK)->assertRedirect();

        $user = User::where('email', 'social@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->approved_at);
        $this->assertTrue((bool) $user->fresh()->status);
        $this->assertTrue(SocialiteUser::where(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1'])->exists());

        $this->get('/admin')->assertRedirect(route('filament.admin.pages.pending-approval'));
    }

    #[RunInSeparateProcess]
    public function test_existing_account_with_a_verified_google_email_is_linked_and_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'social@example.com', 'approved_at' => now(), 'status' => true]);
        $this->googleReturns();

        $this->get(self::CALLBACK)->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame(1, $user->socialiteUsers()->count());
    }

    #[RunInSeparateProcess]
    public function test_unverified_google_email_is_sent_back_to_the_login_page_not_a_403(): void
    {
        $user = User::factory()->create(['email' => 'social@example.com', 'approved_at' => now(), 'status' => true]);
        $this->googleReturns(['raw' => ['email_verified' => false]]);

        $this->assertRefused(__('The email address from this provider is not verified.'));
        $this->assertSame(0, $user->socialiteUsers()->count());
    }

    #[RunInSeparateProcess]
    public function test_removed_account_is_refused_instead_of_failing_on_the_unique_email(): void
    {
        User::factory()->create(['email' => 'social@example.com'])->delete();
        $this->googleReturns();

        $this->assertRefused(__('This account has been removed. Contact an administrator.'));
        $this->assertSame(1, User::withTrashed()->count());
    }

    #[RunInSeparateProcess]
    public function test_provider_without_an_email_is_refused(): void
    {
        $this->googleReturns(['email' => null]);

        $this->assertRefused(__('The provider did not share your email address.'));
        $this->assertSame(0, User::count());
    }

    #[RunInSeparateProcess]
    public function test_deactivated_user_signing_in_with_google_is_signed_out_with_the_reason(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['email' => 'social@example.com', 'approved_at' => now(), 'status' => false]);
        SocialiteUser::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
        $this->googleReturns();

        // the plugin signs in without asking canAccessPanel(); the panel's auth middleware catches it
        $this->get(self::CALLBACK);
        $this->get('/admin')->assertRedirect('/admin/login')->assertSessionHas('filament.notifications');
        $this->assertGuest();
    }
}
