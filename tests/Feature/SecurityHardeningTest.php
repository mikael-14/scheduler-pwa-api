<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailChangedNotice;
use App\Notifications\PwaVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The security review's fixes: request limits, email verification (and the "pre-hijacking" it
 * stops), confirming email changes, the registration honeypot, headers and CORS.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['approved_at' => now(), 'status' => true], $attributes));
    }

    // ---- request limits -------------------------------------------------------------------

    public function test_the_api_has_a_general_limit_per_account(): void
    {
        Sanctum::actingAs($this->user());

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/user')->assertOk();
        }

        $this->getJson('/api/user')->assertStatus(429);
    }

    public function test_changes_through_the_generated_handlers_have_the_tighter_writes_limit(): void
    {
        $routes = Route::getRoutes();

        foreach (['api.admin.schedules.create', 'api.admin.schedules.update', 'api.admin.schedules.join', 'api.admin.schedules.approve', 'api.admin.schedules.take-slot', 'api.admin.schedule-types.delete'] as $name) {
            $this->assertContains('throttle:writes', $routes->getByName($name)->gatherMiddleware(), $name);
        }

        foreach (['api.admin.schedules.view_any', 'api.admin.schedules.view', 'api.admin.schedules.pending'] as $name) {
            $this->assertNotContains('throttle:writes', $routes->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_profile_changes_count_towards_the_writes_limit(): void
    {
        Sanctum::actingAs($this->user());

        for ($i = 0; $i < 20; $i++) {
            $this->patchJson('/api/user', ['name' => "Name {$i}"])->assertOk();
        }

        $this->patchJson('/api/user', ['name' => 'One too many'])->assertStatus(429);
    }

    public function test_a_schedule_takes_at_most_50_people_per_request(): void
    {
        Permission::firstOrCreate(['name' => 'create_schedule', 'guard_name' => 'web']);
        $user = $this->user();
        $user->givePermissionTo('create_schedule');
        Sanctum::actingAs($user);

        $people = array_map(fn($id) => ['user_id' => $id], range(1, 51));

        $this->postJson('/api/admin/schedules', ['start' => now()->addDay()->toDateTimeString(), 'schedule_users' => $people])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedule_users');
    }

    public function test_login_is_limited_per_email_even_from_many_addresses(): void
    {
        $this->user(['email' => 'target@example.com']);

        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => "guess-{$i}"])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/auth/login', ['email' => 'target@example.com', 'password' => 'guess-x'])
            ->assertStatus(429);

        // another account is not affected
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.2'])
            ->postJson('/api/auth/login', ['email' => 'someone@example.com', 'password' => 'whatever'])
            ->assertStatus(422);
    }

    public function test_the_app_login_route_is_the_one_answering_not_the_plugins(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/auth/login', 'POST'));

        $this->assertSame(\App\Http\Controllers\Api\AuthController::class . '@login', $route->getActionName());
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }

    public function test_an_account_without_a_password_cannot_sign_in_with_one(): void
    {
        $this->user(['email' => 'social@example.com', 'password' => null]);

        $this->postJson('/api/auth/login', ['email' => 'social@example.com', 'password' => 'anything'])
            ->assertStatus(422);
    }

    // ---- registration and email verification ----------------------------------------------

    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/register', array_merge([
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
        ], $overrides));
    }

    public function test_the_registration_honeypot_turns_bots_away(): void
    {
        $this->register(['website' => 'http://spam.example'])->assertStatus(422);

        $this->assertSame(0, User::count());
    }

    public function test_registration_is_limited_per_address(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->register(['email' => "new{$i}@example.com"])->assertCreated();
        }

        $this->register(['email' => 'new9@example.com'])->assertStatus(429);
    }

    public function test_a_password_sign_up_must_confirm_its_email_before_the_api_opens(): void
    {
        Notification::fake();

        $response = $this->register()->assertCreated()->assertJsonPath('user.email_verified_at', null);
        $user = User::where('email', 'new@example.com')->firstOrFail();
        Notification::assertSentTo($user, PwaVerifyEmail::class);

        // approved by an admin, but the email is still unconfirmed
        $user->forceFill(['approved_at' => now()])->save();
        $headers = ['Authorization' => 'Bearer ' . $response->json('token'), 'Accept' => 'application/json'];

        $this->getJson('/api/user', $headers)->assertOk();
        $this->getJson('/api/notifications', $headers)->assertStatus(403)->assertJsonPath('code', 'email_unverified');
    }

    /** The values the emailed PWA link carries, as the PWA posts them back. */
    private function verifyPath(User $user): string
    {
        parse_str(parse_url(PwaVerifyEmail::urlFor($user), PHP_URL_QUERY), $q);

        return "/api/auth/email/verify/{$q['id']}/{$q['hash']}?" . http_build_query(['expires' => $q['expires'], 'signature' => $q['signature']]);
    }

    public function test_the_emailed_link_confirms_the_email_without_signing_in(): void
    {
        $user = $this->user(['email_verified_at' => null]);

        $this->assertStringStartsWith(rtrim(config('app.pwa_url'), '/') . '/verify-email?', PwaVerifyEmail::urlFor($user));

        $this->postJson($this->verifyPath($user))->assertOk()->assertJsonPath('verified', true);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // using it again is harmless
        $this->postJson($this->verifyPath($user))->assertOk();
    }

    public function test_a_forged_expired_or_outdated_link_is_refused(): void
    {
        $user = $this->user(['email_verified_at' => null, 'email' => 'old@example.com']);
        $path = $this->verifyPath($user);

        // signature changed
        $this->postJson(preg_replace('/signature=[^&]+/', 'signature=forged', $path))->assertStatus(403)->assertJsonPath('code', 'invalid_link');

        // another account's id with this signature
        $other = $this->user(['email_verified_at' => null]);
        $this->postJson(str_replace("/verify/{$user->id}/", "/verify/{$other->id}/", $path))->assertStatus(403);

        // the email changed since the link was sent
        $user->forceFill(['email' => 'new@example.com'])->save();
        $this->postJson($path)->assertStatus(403);

        // expired
        $user->forceFill(['email' => 'old@example.com'])->save();
        $this->travel(config('auth.verification.expire', 60) + 1)->minutes();
        $this->postJson($path)->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_can_be_sent_again(): void
    {
        Notification::fake();
        $user = $this->user(['email_verified_at' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/resend')->assertOk()->assertJsonPath('verified', false);
        Notification::assertSentTo($user, PwaVerifyEmail::class);
    }

    public function test_a_password_reset_proves_the_email(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-1',
            'password_confirmation' => 'brand-new-1',
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    // ---- pre-hijacking through Google -----------------------------------------------------

    private function googleSignsIn(string $email, bool $verified = true, string $id = 'g-1'): \Illuminate\Testing\TestResponse
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('with')->andReturnUsing(function (array $params) use ($driver) {
            $driver->state = $params['state'] ?? null;

            return $driver;
        });
        $driver->shouldReceive('redirect')->andReturnUsing(fn() => redirect()->away('https://accounts.example/auth?state=' . $driver->state));
        $driver->shouldReceive('user')->andReturn(
            (new ProviderUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'name' => 'Real Owner', 'email' => $email, 'avatar' => null])
        );
        $factory = Mockery::mock();
        $factory->shouldReceive('buildProvider')->andReturn($driver);
        Socialite::swap($factory);

        $url = $this->getJson('/api/auth/google/redirect')->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $this->get("/api/auth/google/callback?state={$query['state']}&code=abc");
    }

    public function test_an_unconfirmed_account_made_with_someone_elses_email_loses_its_password_when_the_owner_signs_in_with_google(): void
    {
        // the attacker registered with the victim's address and knows this password
        $planted = $this->user(['email' => 'victim@example.com', 'email_verified_at' => null, 'password' => Hash::make('attacker-pw')]);
        $planted->createToken('attacker phone');

        $this->googleSignsIn('victim@example.com')->assertRedirectContains('/auth/callback#token=');

        $planted->refresh();
        $this->assertNull($planted->password);
        $this->assertTrue($planted->hasVerifiedEmail());
        // only the token the owner just got is left
        $this->assertSame(1, $planted->tokens()->count());
        $this->assertSame(0, $planted->tokens()->where('name', 'attacker phone')->count());

        $this->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => 'attacker-pw'])->assertStatus(422);
    }

    public function test_a_confirmed_account_keeps_its_password_when_google_is_linked(): void
    {
        $user = $this->user(['email' => 'me@example.com', 'password' => Hash::make('my-own-pw')]);

        $this->googleSignsIn('me@example.com')->assertRedirectContains('/auth/callback#token=');

        $this->assertTrue(Hash::check('my-own-pw', $user->fresh()->password));
    }

    public function test_a_new_google_account_is_verified_but_an_unverified_google_email_is_not(): void
    {
        $this->googleSignsIn('fresh@example.com');
        $this->assertTrue(User::where('email', 'fresh@example.com')->first()->hasVerifiedEmail());

        $this->googleSignsIn('unverified@example.com', verified: false, id: 'g-2');
        $this->assertFalse(User::where('email', 'unverified@example.com')->first()->hasVerifiedEmail());
    }

    // ---- email change ---------------------------------------------------------------------

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $user = $this->user(['email' => 'me@example.com', 'password' => Hash::make('my-own-pw')]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/user', ['email' => 'thief@example.com'])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/user', ['email' => 'thief@example.com', 'current_password' => 'wrong'])->assertStatus(422);

        $this->assertSame('me@example.com', $user->fresh()->email);

        // other fields still change without it
        $this->patchJson('/api/user', ['name' => 'Renamed', 'email' => 'me@example.com'])->assertOk();
    }

    public function test_a_changed_email_warns_the_old_address_and_must_be_confirmed(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'me@example.com', 'password' => Hash::make('my-own-pw')]);
        $user->createToken('other device');
        $current = $user->createToken('this device');

        $this->withHeader('Authorization', 'Bearer ' . $current->plainTextToken)
            ->patchJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'my-own-pw'])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        $user->refresh();
        Notification::assertSentOnDemand(EmailChangedNotice::class, fn($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'me@example.com');
        Notification::assertSentTo($user, PwaVerifyEmail::class);
        $this->assertSame(['this device'], $user->tokens()->pluck('name')->all());
    }

    // ---- headers and CORS -----------------------------------------------------------------

    public function test_responses_carry_the_security_headers(): void
    {
        foreach (['/api/user', '/admin/login'] as $url) {
            $response = $this->get($url, ['Accept' => 'application/json']);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_only_the_pwa_may_call_the_api_from_a_browser(): void
    {
        $pwa = rtrim(config('app.pwa_url'), '/');
        $preflight = fn(string $origin) => $this->call('OPTIONS', '/api/auth/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertSame($pwa, $preflight($pwa)->headers->get('Access-Control-Allow-Origin'));
        // never echoed back: the browser refuses the answer for any other page
        $this->assertNotSame('https://evil.example', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
    }

    // ---- admin panel ----------------------------------------------------------------------

    public function test_an_unconfirmed_account_in_the_panel_goes_to_the_verify_screen_without_looping(): void
    {
        $user = $this->user(['email_verified_at' => null, 'approved_at' => null]);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('filament.admin.auth.email-verification.prompt'));
        $this->actingAs($user)->get(route('filament.admin.auth.email-verification.prompt'))->assertOk();

        // once confirmed, the pending approval page as before
        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->get('/admin')->assertRedirect(route('filament.admin.pages.pending-approval'));
    }
}
