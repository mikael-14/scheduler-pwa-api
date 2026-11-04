<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The README's role matrix (Permissions and roles → Roles), without the "optional" boxes: each
 * role opens exactly the admin pages the README says. Keep the two in step.
 */
class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // ~20 full panel pages per role: more than the default 128M
        ini_set('memory_limit', '512M');
    }

    private const SEE = ['view_any_schedule', 'view_schedule'];
    private const JOIN = ['participate_schedule', 'cancel_participation_schedule'];
    private const BOOK = ['create_schedule', 'update_schedule', 'delete_schedule'];
    private const ALONE = ['independent_schedule', 'all_day_schedule'];
    private const APPROVE = ['approve_schedule', 'view_inactive_schedule'];
    private const STAFF = ['update_status_schedule', 'create_any_schedule', 'update_any_schedule', 'delete_any_schedule', 'update_past_schedule'];
    private const TYPES = ['view_any_schedule_type', 'view_schedule_type', 'create_schedule_type', 'update_schedule_type', 'delete_schedule_type'];
    private const USERS = ['view_any_user', 'create_user', 'update_any_user', 'approve_user', 'assign_role_user', 'delete_user', 'restore_user', 'restore_any_user', 'view_owned_user', 'update_owned_user'];
    private const ROLES = ['view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role'];
    private const ADMINISTRATION = ['view_any_audit', 'view_audit', 'restore_audit', 'view_any_notification_log', 'view_notification_log', 'send_notification_log'];

    private function matrix(): array
    {
        return [
            'Admin' => [...self::SEE, ...self::JOIN, ...self::BOOK, ...self::ALONE, ...self::APPROVE, ...self::STAFF, ...self::TYPES, ...self::USERS, ...self::ROLES, ...self::ADMINISTRATION],
            'Approver' => [...self::SEE, ...self::JOIN, ...self::BOOK, ...self::ALONE, ...self::APPROVE],
            'Voluntário Experiente' => [...self::SEE, ...self::JOIN, ...self::BOOK, ...self::ALONE],
            'Voluntário Novo' => [...self::SEE, ...self::JOIN, ...self::BOOK],
            'Externo' => [...self::SEE, ...self::JOIN],
        ];
    }

    /** page => the roles that open it (the rest: 403) */
    private function pages(Schedule $others, ScheduleType $type, User $volunteer, Role $novo): array
    {
        $everyone = ['Admin', 'Approver', 'Voluntário Experiente', 'Voluntário Novo', 'Externo'];
        $bookers = ['Admin', 'Approver', 'Voluntário Experiente', 'Voluntário Novo'];

        return [
            '/admin' => $everyone,
            '/admin/profile' => $everyone,
            '/admin/schedule-calendar' => $everyone,
            '/admin/schedules' => $everyone,
            "/admin/schedules/{$others->id}" => $everyone,
            '/admin/schedules/create' => $bookers,
            // someone else's schedule: only who may edit anyone's
            "/admin/schedules/{$others->id}/edit" => ['Admin'],
            '/admin/approvals' => ['Admin', 'Approver'],
            '/admin/schedule-types' => ['Admin'],
            '/admin/schedule-types/create' => ['Admin'],
            "/admin/schedule-types/{$type->id}/edit" => ['Admin'],
            '/admin/users' => ['Admin'],
            '/admin/users/create' => ['Admin'],
            "/admin/users/{$volunteer->id}/edit" => ['Admin'],
            '/admin/shield/roles' => ['Admin'],
            '/admin/shield/roles/create' => ['Admin'],
            // a smaller role: the Admin may edit it (never their own, see below)
            "/admin/shield/roles/{$novo->id}/edit" => ['Admin'],
            '/admin/audits' => ['Admin'],
            '/admin/authentication-logs' => ['Admin'],
            '/admin/notifications' => ['Admin'],
            // tokens: super admin only
            '/admin/tokens' => [],
        ];
    }

    public static function roleNames(): array
    {
        return array_map(fn(string $name) => [$name], ['Admin', 'Approver', 'Voluntário Experiente', 'Voluntário Novo', 'Externo']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roleNames')]
    public function test_each_role_opens_exactly_the_pages_the_readme_says(string $only): void
    {
        $roles = [];
        foreach ($this->matrix() as $name => $permissions) {
            $roles[$name] = Role::create(['name' => $name, 'guard_name' => 'web']);
            foreach ($permissions as $permission) {
                $roles[$name]->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
            }
        }
        // tokens exist but nobody here has them
        foreach (['view_any_token', 'view_token', 'create_token', 'update_token', 'delete_token'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $volunteer = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $volunteer->assignRole($roles['Voluntário Novo']);
        $type = ScheduleType::create(['name' => 'Walks', 'color' => '#123456', 'status' => true, 'range' => true, 'all_day' => false]);
        $start = now()->addDays(2)->setTime(10, 0);
        $others = Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $volunteer->id, 'status' => ScheduleStatus::Approved,
            'start' => $start, 'end' => $start->copy()->addHour(),
        ]));

        Filament::setCurrentPanel('admin');
        $wrong = [];
        foreach (array_intersect_key($roles, [$only => true]) as $name => $role) {
            $person = User::factory()->create(['approved_at' => now(), 'status' => true]);
            $person->assignRole($role);
            $this->actingAs($person);

            foreach ($this->pages($others, $type, $volunteer, $roles['Voluntário Novo']) as $url => $allowed) {
                $status = $this->get($url)->status();
                $expected = in_array($name, $allowed, true) ? 200 : 403;
                // denied: 403, or 404 where the record is out of reach (another user's page)
                $denied = $expected === 403 && in_array($status, [403, 404], true);
                if ($status !== $expected && ! $denied) {
                    $wrong[] = "$name $url: $status (expected $expected)";
                }
            }

            // never their own role
            if ($name === 'Admin') {
                $status = $this->get("/admin/shield/roles/{$role->id}/edit")->status();
                if ($status !== 403) {
                    $wrong[] = "Admin edits the Admin role: $status";
                }
            }
            $this->app['auth']->forgetGuards();
        }

        $this->assertSame([], $wrong, implode("\n", $wrong));
    }
}
