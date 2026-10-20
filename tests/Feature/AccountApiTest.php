<?php

namespace Tests\Feature;

use App\Models\SocialiteUser;
use App\Models\User;
use App\Notifications\PwaResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The PWA's account screen: photo, password, signed-in devices and "forgot password". */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['approved_at' => now(), 'status' => true], $attributes));
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    public function test_permissions_sent_to_the_pwa_include_the_ones_from_roles(): void
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => 'volunteer', 'guard_name' => 'web']);
        foreach (['create_schedule', 'view_any_schedule'] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->syncPermissions(['create_schedule', 'view_any_schedule']);
        $direct = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'approve_schedule', 'guard_name' => 'web']);

        $user = $this->user(['email' => 'me@example.com', 'password' => Hash::make('secret-123')]);
        $user->assignRole($role);
        $user->givePermissionTo($direct);

        // at sign-in and on every refresh: role permissions and direct ones
        $expected = ['approve_schedule', 'create_schedule', 'view_any_schedule'];
        $this->postJson('/api/auth/login', ['email' => 'me@example.com', 'password' => 'secret-123'])
            ->assertOk()->assertJsonPath('permissions', $expected);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/user')->assertOk()->assertJsonPath('permissions', $expected);
    }

    public function test_profile_says_whether_there_is_a_password_and_which_logins_are_linked(): void
    {
        $social = $this->user(['password' => null]);
        SocialiteUser::create(['user_id' => $social->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        Sanctum::actingAs($social);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('user.has_password', false)
            ->assertJsonPath('user.providers', ['google'])
            ->assertJsonMissingPath('user.password');

        // a social-only account sets a first password without a current one
        $this->patchJson('/api/user', ['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertOk()
            ->assertJsonPath('user.has_password', true);
        $this->assertTrue(Hash::check('new-secret-1', $social->fresh()->password));
    }

    public function test_profile_photo_upload_replace_and_remove(): void
    {
        Storage::fake('public');
        $user = $this->user();
        Sanctum::actingAs($user);

        $first = $this->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()->json('user');
        $firstPath = $user->fresh()->avatar_url;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertStringStartsWith('http', $first['avatar_url']);

        // a new photo replaces the old file
        $this->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->image('me2.png')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($firstPath);

        // not an image, or too big
        $this->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->post('/api/user/avatar', ['avatar' => UploadedFile::fake()->image('huge.jpg')->size(3000)], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $secondPath = $user->fresh()->avatar_url;
        $this->deleteJson('/api/user/avatar')->assertOk()->assertJsonPath('user.avatar_url', null);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_devices_are_listed_and_others_can_be_signed_out(): void
    {
        $user = $this->user();
        $phone = $user->createToken('Chrome · Android')->plainTextToken;
        $laptop = $user->createToken('Firefox · Windows')->plainTextToken;
        $tablet = $user->createToken('Safari · iPad');

        $list = $this->getJson('/api/user/sessions', $this->bearer($phone))->assertOk()->json('data');
        $this->assertCount(3, $list);
        $this->assertSame(['Chrome · Android'], collect($list)->where('current', true)->pluck('name')->all());

        // this device cannot be "revoked" here, another one can
        $currentId = collect($list)->firstWhere('current', true)['id'];
        $this->app['auth']->forgetGuards();
        $this->deleteJson("/api/user/sessions/{$currentId}", [], $this->bearer($phone))->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->deleteJson("/api/user/sessions/{$tablet->accessToken->id}", [], $this->bearer($phone))->assertOk();

        // and someone else's token is not found
        $stranger = $this->user()->createToken('x');
        $this->app['auth']->forgetGuards();
        $this->deleteJson("/api/user/sessions/{$stranger->accessToken->id}", [], $this->bearer($phone))->assertNotFound();

        // sign out all the others
        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/user/sessions', [], $this->bearer($phone))->assertOk();
        $this->assertSame(['Chrome · Android'], $user->tokens()->pluck('name')->all());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', $this->bearer($laptop))->assertUnauthorized();
    }

    public function test_sign_in_names_the_token_after_the_device(): void
    {
        $this->user(['email' => 'me@example.com', 'password' => Hash::make('secret-123')]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36')
            ->postJson('/api/auth/login', ['email' => 'me@example.com', 'password' => 'secret-123'])
            ->assertOk();

        $this->assertSame('Chrome · Android', User::firstWhere('email', 'me@example.com')->tokens()->value('name'));
    }

    public function test_forgot_password_emails_a_pwa_link_and_never_reveals_who_has_an_account(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'me@example.com']);

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'me@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');
        $this->assertSame($known, $unknown);

        Notification::assertSentTo($user, PwaResetPassword::class, function (PwaResetPassword $notification) use ($user) {
            $html = $notification->toMail($user)->render();

            return str_contains($html, rtrim(config('app.pwa_url'), '/') . '/reset-password?token=');
        });
    }

    public function test_reset_password_sets_it_and_signs_out_every_device(): void
    {
        $user = $this->user(['email' => 'me@example.com']);
        $user->createToken('old phone');
        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

        // wrong token
        $this->postJson('/api/auth/reset-password', [
            'token' => 'nope', 'email' => 'me@example.com', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'me@example.com', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());

        // a link works once
        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'me@example.com', 'password' => 'another-1', 'password_confirmation' => 'another-1',
        ])->assertUnprocessable();
    }
}
