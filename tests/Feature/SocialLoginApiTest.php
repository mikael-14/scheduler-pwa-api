<?php

namespace Tests\Feature;

use App\Models\SocialiteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery;
use Tests\TestCase;

/** The PWA's Google / Facebook login (stateless Socialite, token handed over in the URL fragment). */
class SocialLoginApiTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(?ProviderUser $user = null): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('with')->andReturnUsing(function (array $params) use ($driver) {
            $driver->state = $params['state'] ?? null;

            return $driver;
        });
        $driver->shouldReceive('redirect')->andReturnUsing(
            fn() => redirect()->away('https://accounts.example/auth?state=' . $driver->state)
        );
        if ($user) {
            $driver->shouldReceive('user')->andReturn($user);
        }

        // swap, so a later call replaces the driver instead of stacking behind the first one
        $factory = Mockery::mock();
        $factory->shouldReceive('buildProvider')->andReturn($driver);
        Socialite::swap($factory);
    }

    private function providerUser(array $overrides = []): ProviderUser
    {
        return (new ProviderUser)->setRaw($overrides['raw'] ?? ['email_verified' => true])->map([
            'id' => $overrides['id'] ?? 'g-1',
            'name' => 'Social Person',
            'email' => $overrides['email'] ?? 'social@example.com',
            'avatar' => null,
        ]);
    }

    private function issueState(string $locale = 'pt'): string
    {
        $this->fakeProvider();
        $url = $this->withHeader('Accept-Language', $locale)->getJson('/api/auth/google/redirect')->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query['state'];
    }

    public function test_redirect_issues_a_state_and_rejects_unknown_providers(): void
    {
        $state = $this->issueState();
        $this->assertSame(40, strlen($state));

        $this->getJson('/api/auth/github/redirect')->assertStatus(400);
    }

    public function test_callback_without_a_valid_state_is_refused_and_creates_nothing(): void
    {
        $this->fakeProvider($this->providerUser());

        foreach (['/api/auth/google/callback', '/api/auth/google/callback?state=forged'] as $url) {
            $this->get($url)->assertRedirectContains('/login?error=auth_failed');
        }

        $this->assertSame(0, User::count());
    }

    public function test_new_social_user_gets_a_fragment_token_pending_approval_and_the_pwa_language(): void
    {
        $state = $this->issueState('en');
        $this->fakeProvider($this->providerUser());

        // the browser coming back from Google speaks Portuguese; the PWA user chose English
        $response = $this->withHeader('Accept-Language', 'pt')->get("/api/auth/google/callback?state={$state}&code=abc");

        $location = $response->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/auth/callback#token=', $location);
        $this->assertStringNotContainsString('?token=', $location);

        $user = User::where('email', 'social@example.com')->firstOrFail();
        $this->assertSame('en', $user->locale);
        $this->assertNull($user->approved_at);
        $this->assertDatabaseHas('socialite_users', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        // the token works for reading the account state, which is what the PWA does next
        $token = explode('#token=', $location)[1];
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('user.email', 'social@example.com');
    }

    public function test_state_is_single_use(): void
    {
        $state = $this->issueState();
        $this->fakeProvider($this->providerUser());

        $this->get("/api/auth/google/callback?state={$state}")->assertRedirectContains('/auth/callback#token=');
        $this->get("/api/auth/google/callback?state={$state}")->assertRedirectContains('/login?error=auth_failed');
    }

    public function test_unverified_email_never_links_to_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'social@example.com', 'approved_at' => now(), 'status' => true]);
        $state = $this->issueState();
        $this->fakeProvider($this->providerUser(['raw' => ['email_verified' => false]]));

        $this->get("/api/auth/google/callback?state={$state}")->assertRedirectContains('/login?error=auth_failed');
        $this->assertSame(0, SocialiteUser::where('user_id', $existing->id)->count());
    }

    public function test_inactive_accounts_are_sent_back_to_login(): void
    {
        $user = User::factory()->create(['email' => 'social@example.com', 'approved_at' => now(), 'status' => false]);
        $state = $this->issueState();
        $this->fakeProvider($this->providerUser());

        $this->get("/api/auth/google/callback?state={$state}")->assertRedirectContains('/login?error=account_inactive');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_returning_social_user_logs_into_the_same_account(): void
    {
        $user = User::factory()->create(['email' => 'other@example.com', 'approved_at' => now(), 'status' => true]);
        SocialiteUser::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
        $state = $this->issueState();
        $this->fakeProvider($this->providerUser(['email' => 'changed@example.com']));

        $this->get("/api/auth/google/callback?state={$state}")->assertRedirectContains('/auth/callback#token=');
        $this->assertSame(1, User::count());
        $this->assertSame(1, $user->tokens()->count());
    }
}
