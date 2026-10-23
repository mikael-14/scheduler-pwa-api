<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pending_user_is_sent_to_the_pending_page(): void
    {
        $user = User::factory()->create(['approved_at' => null, 'status' => true]);

        $this->actingAs($user)->get('/admin')
            ->assertRedirect(route('filament.admin.pages.pending-approval'));
    }

    public function test_pending_page_shows_the_translated_title_and_logout_button(): void
    {
        $user = User::factory()->create(['approved_at' => null, 'status' => true]);

        $this->actingAs($user)->get(route('filament.admin.pages.pending-approval'))
            ->assertOk()
            ->assertSee(__('Account Pending Approval'))
            ->assertSee($user->email)
            ->assertSee(filament()->getLogoutUrl(), false);
    }

    public function test_pending_user_can_log_out(): void
    {
        $user = User::factory()->create(['approved_at' => null, 'status' => true]);

        $this->actingAs($user)->post(filament()->getLogoutUrl())->assertRedirect();

        $this->assertGuest();
    }

    public function test_approved_user_is_sent_away_from_the_pending_page(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);

        $this->actingAs($user)->get(route('filament.admin.pages.pending-approval'))
            ->assertRedirect(filament()->getUrl());
    }

    public function test_deactivated_pending_user_is_signed_out_not_sent_to_the_pending_page(): void
    {
        $user = User::factory()->create(['approved_at' => null, 'status' => false]);

        $this->actingAs($user)->get('/admin')
            ->assertRedirect(filament()->getLoginUrl());

        $this->assertGuest();
    }

    public function test_guest_on_the_login_page_is_not_redirected(): void
    {
        $this->get(filament()->getLoginUrl())->assertOk();
    }
}
