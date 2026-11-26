<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The service worker swaps a push subscription the browser replaced, with nobody signed in. */
class PushSubscriptionRenewTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/pwa/subscription/renew';

    private function payload(string $old, string $new = 'https://fcm.googleapis.com/fcm/send/new'): array
    {
        return ['old_endpoint' => $old, 'endpoint' => $new, 'keys' => ['p256dh' => 'new-key', 'auth' => 'new-auth']];
    }

    public function test_the_old_subscription_is_replaced_by_the_new_one(): void
    {
        $user = User::factory()->create(['status' => true, 'approved_at' => now()]);
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/old', 'key', 'auth');
        $user->updatePushSubscription('https://push.example/laptop', 'key', 'auth');

        $this->postJson(self::URL, $this->payload('https://fcm.googleapis.com/fcm/send/old'))->assertOk();

        $this->assertEqualsCanonicalizing(
            ['https://fcm.googleapis.com/fcm/send/new', 'https://push.example/laptop'],
            $user->pushSubscriptions()->pluck('endpoint')->all()
        );
        $new = $user->pushSubscriptions()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/new')->first();
        $this->assertSame(['new-key', 'new-auth'], [$new->public_key, $new->auth_token]);
    }

    public function test_the_same_endpoint_only_gets_its_new_keys(): void
    {
        $user = User::factory()->create(['status' => true, 'approved_at' => now()]);
        $user->updatePushSubscription('https://push.example/phone', 'key', 'auth');

        $this->postJson(self::URL, $this->payload('https://push.example/phone', 'https://push.example/phone'))->assertOk();

        $this->assertSame(['new-key'], $user->pushSubscriptions()->pluck('public_key')->all());
    }

    public function test_an_unknown_endpoint_or_an_account_that_may_not_get_push_changes_nothing(): void
    {
        $this->postJson(self::URL, $this->payload('https://push.example/guessed'))->assertNotFound();

        $off = User::factory()->create(['status' => false, 'approved_at' => now()]);
        $off->updatePushSubscription('https://push.example/off', 'key', 'auth');
        $this->postJson(self::URL, $this->payload('https://push.example/off'))->assertNotFound();

        $this->assertSame(['https://push.example/off'], $off->pushSubscriptions()->pluck('endpoint')->all());
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/new']);
    }

    public function test_a_new_endpoint_must_be_an_address(): void
    {
        $this->postJson(self::URL, $this->payload('https://push.example/old', 'not an address'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');
    }
}
