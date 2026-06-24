<?php

namespace Tests\Unit\Support;

use STS\Models\User;
use STS\Support\UserLocale;
use Tests\TestCase;

class UserLocaleTest extends TestCase
{
    public function test_resolve_returns_user_locale_when_present(): void
    {
        $user = User::factory()->make(['locale' => 'en']);

        $this->assertSame('en', UserLocale::resolve($user));
    }

    public function test_resolve_falls_back_to_explicit_fallback_when_user_locale_is_missing(): void
    {
        $user = User::factory()->make(['locale' => null]);

        $this->assertSame('arg', UserLocale::resolve($user, 'arg'));
    }

    public function test_resolve_falls_back_to_app_locale_when_user_locale_is_missing(): void
    {
        config(['app.locale' => 'arg']);
        $user = User::factory()->make(['locale' => null]);

        $this->assertSame('arg', UserLocale::resolve($user));
    }

    public function test_resolve_falls_back_to_app_locale_when_user_is_null(): void
    {
        config(['app.locale' => 'en']);

        $this->assertSame('en', UserLocale::resolve(null));
    }

    public function test_with_locale_runs_callback_under_requested_locale_and_restores_previous(): void
    {
        app()->setLocale('arg');

        $observed = UserLocale::withLocale('en', function () {
            return app()->getLocale();
        });

        $this->assertSame('en', $observed);
        $this->assertSame('arg', app()->getLocale());
    }
}
