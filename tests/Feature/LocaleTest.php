<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private function guestLoginError(string $acceptLanguage): string
    {
        return $this->postJson('/api/auth/login', [], ['Accept-Language' => $acceptLanguage])
            ->assertStatus(422)
            ->json('errors.email.0');
    }

    public function test_default_language_is_portuguese(): void
    {
        $this->assertSame('pt', config('app.locale'));
        $this->assertSame('pt', config('app-locales.default'));
    }

    public function test_guest_errors_follow_accept_language(): void
    {
        $this->assertStringContainsString('obrigatório', $this->guestLoginError('pt-PT,pt;q=0.9,en;q=0.5'));
        $this->assertStringContainsString('required', $this->guestLoginError('en'));
    }

    public function test_unsupported_or_empty_language_falls_back_to_portuguese(): void
    {
        $this->assertStringContainsString('obrigatório', $this->guestLoginError('fr-FR,fr;q=0.9'));
        $this->assertStringContainsString('obrigatório', $this->guestLoginError(''));
    }

    public function test_user_with_english_profile_gets_english_even_if_the_header_says_portuguese(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => 'en']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/user', ['email' => 'not-an-email'], ['Accept-Language' => 'pt'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.');
    }

    public function test_user_with_portuguese_profile_gets_portuguese_even_if_the_header_says_english(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => 'pt']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/user', ['email' => 'not-an-email'], ['Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'O campo email deve ser um endereço de email válido.');
    }

    public function test_pending_message_is_translated(): void
    {
        $user = User::factory()->create(['approved_at' => null, 'status' => true, 'locale' => 'pt']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/schedules', ['Accept-Language' => 'en'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'A sua conta está a aguardar aprovação.');
    }

    public function test_register_stores_the_visitor_language_and_defaults_to_portuguese(): void
    {
        $payload = ['name' => 'A', 'email' => 'a@x.test', 'password' => 'password-1', 'password_confirmation' => 'password-1'];

        $this->postJson('/api/auth/register', $payload + ['locale' => 'en'])->assertCreated();
        $this->assertSame('en', User::where('email', 'a@x.test')->value('locale'));

        $this->postJson('/api/auth/register', array_merge($payload, ['email' => 'b@x.test']), ['Accept-Language' => 'pt'])
            ->assertCreated();
        $this->assertSame('pt', User::where('email', 'b@x.test')->value('locale'));
    }

    public function test_language_switcher_only_accepts_supported_languages_and_saves_the_choice(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => 'pt']);

        $this->actingAs($user)->get('/filament/switch-language/xx')->assertNotFound();
        $this->assertSame('pt', $user->fresh()->locale);

        $this->actingAs($user)->from('/admin')->get('/filament/switch-language/en')->assertRedirect('/admin');
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_a_guest_language_choice_is_remembered_in_a_cookie(): void
    {
        $response = $this->from('/admin/login')->get('/filament/switch-language/en');

        $response->assertRedirect('/admin/login');
        $response->assertCookie('filament_language_switcher_locale');
    }

    public function test_new_accounts_start_in_the_language_in_use(): void
    {
        app()->setLocale('en');

        $this->assertSame('en', User::factory()->create()->locale);

        $this->assertSame('pt', User::factory()->create(['locale' => 'pt'])->locale);
    }

    public function test_patch_profile_can_change_the_language(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => 'pt']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/user', ['locale' => 'en'])->assertOk()->assertJsonPath('user.locale', 'en');
        $this->assertSame('en', $user->fresh()->locale);

        $this->patchJson('/api/user', ['locale' => 'fr'])->assertStatus(422)->assertJsonValidationErrors('locale');
    }
}
