<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every API route is closed by default: signed in, active and approved, and on the schedule /
 * type routes also a permission. Only signing in and the own account are open as listed here,
 * so a new route that forgets its guards fails this test.
 */
class ApiRouteGuardsTest extends TestCase
{
    /** No sign-in needed (rate limited). */
    private const PUBLIC = [
        'api/auth/login', 'api/auth/register', 'api/auth/forgot-password', 'api/auth/reset-password',
        'api/auth/{provider}/redirect', 'api/auth/{provider}/callback',
    ];

    /** Signed in, but also while waiting for approval: the own account and its session. */
    private const PENDING_TOO = [
        'api/auth/logout', 'api/auth/refresh',
        'api/user', 'api/user/avatar', 'api/user/sessions', 'api/user/sessions/{id}',
    ];

    /** Schedule routes for every approved account, no permission: counts only, never who. */
    private const NO_PERMISSION = [
        'api/{panel}/schedules/primary-week', // the PWA home strip (PrimaryWeekHandler)
    ];

    private function apiRoutes(): array
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn(Route $route) => Str::startsWith($route->uri(), 'api/'))
            ->all();
    }

    private function middleware(Route $route): array
    {
        return collect($route->gatherMiddleware())->map(fn($m) => is_string($m) ? $m : get_class($m))->all();
    }

    private function has(array $middleware, string $needle): bool
    {
        return collect($middleware)->contains(fn(string $m) => Str::contains($m, $needle));
    }

    public function test_every_api_route_is_signed_in_active_and_approved_unless_listed(): void
    {
        $this->assertNotEmpty($this->apiRoutes());

        foreach ($this->apiRoutes() as $route) {
            $uri = $route->uri();
            if (in_array($uri, self::PUBLIC, true)) {
                continue;
            }

            $middleware = $this->middleware($route);
            $this->assertTrue($this->has($middleware, 'auth:sanctum') || $this->has($middleware, 'Authenticate:sanctum'), "$uri: not signed in");

            if ($uri === 'api/auth/logout') {
                continue; // signing out works even for a deactivated account
            }
            $this->assertTrue($this->has($middleware, 'active') || $this->has($middleware, 'EnsureUserIsActive'), "$uri: deactivated accounts get in");

            if (! in_array($uri, self::PENDING_TOO, true)) {
                $this->assertTrue($this->has($middleware, 'approved') || $this->has($middleware, 'EnsureUserIsApproved'), "$uri: accounts waiting for approval get in");
            }
        }
    }

    public function test_every_schedule_and_type_route_needs_a_permission(): void
    {
        $guarded = collect($this->apiRoutes())
            ->filter(fn(Route $route) => Str::contains($route->uri(), ['/schedules', '/schedule-types']))
            ->reject(fn(Route $route) => in_array($route->uri(), self::NO_PERMISSION, true));

        $this->assertNotEmpty($guarded);

        foreach ($guarded as $route) {
            $this->assertTrue(
                $this->has($this->middleware($route), 'permission') || $this->has($this->middleware($route), 'PermissionMiddleware'),
                $route->uri() . ': no permission checked'
            );
        }
    }
}
