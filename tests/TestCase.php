<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Test data made by "the system", like seeders and scheduled tasks: nobody signed in, so the
     * rules for people (e.g. only booking for yourself, ScheduleAccess::enforceOwner) don't apply.
     * Whoever was signed in is back afterwards.
     */
    protected function asSystem(callable $make): mixed
    {
        // every guard: sanctum also falls back to the web guard's user
        $signedIn = [];
        foreach (array_keys(config('auth.guards')) as $name) {
            $guard = $this->app['auth']->guard($name);
            if ($guard->hasUser()) {
                $signedIn[$name] = $guard->user();
            }
            $guard->forgetUser();
        }

        try {
            return $make();
        } finally {
            foreach ($signedIn as $name => $user) {
                $this->app['auth']->guard($name)->setUser($user);
            }
        }
    }
}
