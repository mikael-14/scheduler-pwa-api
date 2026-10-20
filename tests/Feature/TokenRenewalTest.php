<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Sessions last while the app is used: the PWA swaps its token for a fresh one about daily. */
class TokenRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function forgetGuards(): void
    {
        // each request must authenticate its own token, as separate HTTP requests would
        $this->app['auth']->forgetGuards();
    }

    public function test_refresh_gives_a_new_token_and_the_old_one_stops_after_a_short_grace(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $old = $user->createToken('pwa-login')->plainTextToken;

        $new = $this->postJson('/api/auth/refresh', [], $this->bearer($old))
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_in'])
            ->json('token');
        $this->assertNotSame($old, $new);

        // both work right after the swap (requests on their way, other open tabs)
        $this->forgetGuards();
        $this->getJson('/api/user', $this->bearer($old))->assertOk();
        $this->forgetGuards();
        $this->getJson('/api/user', $this->bearer($new))->assertOk();

        // after the grace period only the new one does
        $this->travel(6)->minutes();
        $this->forgetGuards();
        $this->getJson('/api/user', $this->bearer($old))->assertUnauthorized();
        $this->forgetGuards();
        $this->getJson('/api/user', $this->bearer($new))->assertOk();
    }

    public function test_a_token_used_every_day_never_reaches_the_30_day_limit(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $token = $user->createToken('pwa-login')->plainTextToken;

        // renewed daily for 40 days
        foreach (range(1, 40) as $day) {
            $this->travel(1)->days();
            $this->forgetGuards();
            $token = $this->postJson('/api/auth/refresh', [], $this->bearer($token))->assertOk()->json('token');
        }

        $this->forgetGuards();
        $this->getJson('/api/user', $this->bearer($token))->assertOk();
    }

    public function test_unused_session_still_ends_after_30_days_and_refresh_cannot_revive_it(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $token = $user->createToken('pwa-login')->plainTextToken;

        $this->travel(31)->days();
        $this->postJson('/api/auth/refresh', [], $this->bearer($token))->assertUnauthorized();
    }

    public function test_pending_accounts_can_renew_and_inactive_ones_cannot(): void
    {
        $pending = User::factory()->create(['approved_at' => null, 'status' => true]);
        $this->postJson('/api/auth/refresh', [], $this->bearer($pending->createToken('pwa-login')->plainTextToken))->assertOk();

        $this->forgetGuards();
        $inactive = User::factory()->create(['approved_at' => now(), 'status' => false]);
        $this->postJson('/api/auth/refresh', [], $this->bearer($inactive->createToken('pwa-login')->plainTextToken))
            ->assertForbidden()
            ->assertJson(['code' => 'account_inactive']);
    }

    public function test_expired_tokens_are_pruned_daily(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->createToken('old-session');
        $this->travel(32)->days();
        $user->createToken('current-session');

        Artisan::call('sanctum:prune-expired', ['--hours' => 24]);

        $this->assertSame(['current-session'], PersonalAccessToken::pluck('name')->all());

        $event = collect(app(Schedule::class)->events())
            ->first(fn($event) => str_contains($event->command, 'sanctum:prune-expired'));
        $this->assertNotNull($event, 'sanctum:prune-expired is not scheduled');
        $this->assertSame('0 0 * * *', $event->expression);
    }
}
