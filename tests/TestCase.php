<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
