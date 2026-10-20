<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use OwenIt\Auditing\Models\Audit;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the PWA does shows up in the admin panel's Audits (tapp/filament-auditing) and
 * Authentication log (tapp/filament-authentication-log), with who did it.
 */
class PwaAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        // the "new device" email is not what is tested here
        Notification::fake();
        // Laravel Auditing skips console runs (tests count as console); web/API requests are audited
        config(['audit.console' => true]);
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'approved_at' => now(), 'status' => true, 'email' => 'me@example.com', 'password' => Hash::make('secret-123'),
        ], $attributes));
        $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        foreach (['view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->syncPermissions(['view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule']);
        $user->assignRole($role);

        return $user;
    }

    /** Sign in like the PWA does: a real token, sent as a bearer header. */
    private function signIn(): array
    {
        $token = $this->withHeader('User-Agent', self::UA)
            ->postJson('/api/auth/login', ['email' => 'me@example.com', 'password' => 'secret-123'])
            ->assertOk()->json('token');
        $this->app['auth']->forgetGuards();

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json', 'User-Agent' => self::UA];
    }

    public function test_schedule_created_and_changed_from_the_pwa_is_audited_with_its_author(): void
    {
        $me = $this->member();
        $friend = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $headers = $this->signIn();
        $day = now()->addDays(3)->format('Y-m-d');

        $id = $this->postJson('/api/admin/schedules', [
            'schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00", 'description' => 'Morning shift',
            'schedule_users' => [['user_id' => $friend->id]],
        ], $headers)->assertSuccessful()->json('data.id');

        $this->app['auth']->forgetGuards();
        $this->patchJson("/api/admin/schedules/{$id}", ['description' => 'Afternoon shift'], $headers)->assertOk();

        $audits = Audit::where('auditable_type', Schedule::class)->where('auditable_id', $id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $audits->pluck('event')->all());
        // who did it: the PWA user, not "anonymous"
        $this->assertSame([$me->id, $me->id], $audits->pluck('user_id')->map(fn($v) => (int) $v)->all());
        $this->assertSame(['description' => 'Morning shift'], $audits[1]->old_values);
        $this->assertSame(['description' => 'Afternoon shift'], $audits[1]->new_values);
        $this->assertSame(self::UA, $audits[1]->user_agent);

        // the participant added from the PWA too
        $participantAudit = Audit::where('auditable_type', \App\Models\ScheduleUser::class)->where('event', 'created')->first();
        $this->assertNotNull($participantAudit);
        $this->assertSame($me->id, (int) $participantAudit->user_id);
    }

    public function test_pwa_sign_in_failed_sign_in_and_sign_out_are_in_the_authentication_log(): void
    {
        $me = $this->member();

        $this->withHeader('User-Agent', self::UA)
            ->postJson('/api/auth/login', ['email' => 'me@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
        // the log closes the device's newest entry on sign-out: keep the two apart as in real use
        $this->travel(5)->seconds();
        $headers = $this->signIn();

        $logs = AuthenticationLog::where('authenticatable_id', $me->id)->get();
        $this->assertSame(1, $logs->where('login_successful', false)->count(), 'failed sign-in not logged');
        $success = $logs->firstWhere('login_successful', true);
        $this->assertNotNull($success, 'sign-in not logged');
        $this->assertSame(self::UA, $success->user_agent);
        $this->assertNull($success->logout_at);

        $this->postJson('/api/auth/logout', [], $headers)->assertOk();

        $this->assertNotNull($success->fresh()->logout_at, 'sign-out not logged');
    }

    public function test_password_hashes_never_end_up_in_the_audit_log(): void
    {
        $me = $this->member();
        $headers = $this->signIn();

        $this->patchJson('/api/user', [
            'current_password' => 'secret-123', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ], $headers)->assertOk();

        $values = Audit::where('auditable_type', User::class)->where('auditable_id', $me->id)->get()
            ->flatMap(fn(Audit $audit) => array_merge(array_keys($audit->old_values ?? []), array_keys($audit->new_values ?? [])));
        $this->assertNotContains('password', $values->all());
        $this->assertNotContains('remember_token', $values->all());
    }
}
