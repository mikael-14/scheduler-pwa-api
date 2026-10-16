<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin panel must render in the signed-in user's language, not in the session's or the default.
 * Each test runs in its own process because Filament keeps navigation/label state in statics.
 */
class PanelLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function panelAs(string $locale, string $url = '/admin'): string
    {
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Gate::before(fn($user) => $user->hasRole('super_admin') ? true : null);

        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => $locale]);
        $user->assignRole('super_admin');

        // The session says the opposite, as if the language had been switched on the login page
        $other = $locale === 'en' ? 'pt' : 'en';

        $response = $this->actingAs($user)->withSession(['locale' => $other])->get($url);
        $response->assertOk();

        return $response->getContent();
    }

    #[RunInSeparateProcess]
    public function test_portuguese_profile_renders_the_panel_in_portuguese(): void
    {
        $html = $this->panelAs('pt');

        $this->assertStringContainsString('Painel de Controlo', $html);
        $this->assertStringContainsString('Agendador', $html);
        $this->assertStringContainsString('Administração', $html);
        $this->assertStringNotContainsString('>Dashboard<', $html);
    }

    #[RunInSeparateProcess]
    public function test_english_profile_renders_the_panel_in_english_despite_a_portuguese_session(): void
    {
        $html = $this->panelAs('en');

        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('Scheduler', $html);
        $this->assertStringContainsString('Administration', $html);
        $this->assertStringNotContainsString('Painel de Controlo', $html);
    }

    #[RunInSeparateProcess]
    public function test_audits_page_and_navigation_are_translated(): void
    {
        $html = $this->panelAs('pt', '/admin/audits');

        $this->assertStringContainsString('Auditorias', $html);
        $this->assertStringContainsString('Agendamentos', $html);
        $this->assertStringNotContainsString('>Audits<', $html);
    }

    #[RunInSeparateProcess]
    public function test_profile_page_has_the_notification_preferences(): void
    {
        $html = $this->panelAs('pt', '/admin/profile');

        $this->assertStringContainsString('Notificações por email', $html);
        $this->assertStringContainsString('Notificações push', $html);
        $this->assertStringContainsString('data.email_notification', $html);
        $this->assertStringContainsString('data.push_notification', $html);
        $this->assertStringContainsString('data.locale', $html);
    }

    #[RunInSeparateProcess]
    public function test_admin_can_edit_notification_preferences_of_a_user(): void
    {
        $this->withoutVite();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Gate::before(fn($user) => $user->hasRole('super_admin') ? true : null);
        $admin = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => 'en']);
        $admin->assignRole('super_admin');
        $target = User::factory()->create(['approved_at' => now(), 'status' => true]);

        $html = $this->actingAs($admin)->get("/admin/users/{$target->id}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('Email notifications', $html);
        $this->assertStringContainsString('Push notifications', $html);
    }

    #[RunInSeparateProcess]
    public function test_generated_form_labels_are_translated(): void
    {
        $html = $this->panelAs('pt', '/admin/schedules/create');

        $this->assertStringContainsString('Tipo de agendamento', $html);
        $this->assertStringContainsString('Nota interna', $html);
        $this->assertStringNotContainsString('Schedule type id', $html);
        $this->assertStringNotContainsString('Internal note', $html);
    }
}
