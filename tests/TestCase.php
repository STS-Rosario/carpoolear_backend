<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Drop views before tables during migrate:fresh so wipes stay consistent when SQL views exist (e.g. legacy rating aggregates).
     */
    protected bool $dropViews = true;

    protected function actingAsApiUser($user)
    {
        return $this->actingAs($user, 'api');
    }

    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        // Also set on default guard so the UserLoggin middleware can find the user
        if ($guard === 'api') {
            $this->app['auth']->guard()->setUser($user);
        }

        return $this;
    }
}
