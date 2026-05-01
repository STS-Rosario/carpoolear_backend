<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use STS\Http\Middleware\RedirectIfAuthenticated;
use STS\Models\User;
use Tests\TestCase;

class RedirectIfAuthenticatedTest extends TestCase
{
    public function test_guest_proceeds_to_next(): void
    {
        $this->assertGuest('web');

        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');
        $ran = false;

        $response = $middleware->handle($request, function () use (&$ran) {
            $ran = true;

            return response('login-form', 200);
        });

        $this->assertTrue($ran);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('login-form', $response->getContent());
    }

    public function test_authenticated_user_redirects_to_home(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);

        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, fn () => response('should-not-run', 200));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertSame(url('/'), $response->headers->get('Location'));
    }

    public function test_explicit_web_guard_redirects_when_logged_in(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);

        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/register', 'GET');

        $response = $middleware->handle($request, fn () => response('register', 200), 'web');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url('/'), $response->headers->get('Location'));
    }

    public function test_explicit_api_guard_does_not_redirect_when_only_web_guard_is_authenticated(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);
        $this->assertGuest('api');

        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/register', 'GET');
        $ran = false;

        $response = $middleware->handle($request, function () use (&$ran) {
            $ran = true;

            return response('register-form', 200);
        }, 'api');

        $this->assertTrue($ran);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('register-form', $response->getContent());
    }

    public function test_explicit_api_guard_redirects_when_api_guard_is_authenticated(): void
    {
        $user = User::factory()->create();
        Auth::guard('api')->login($user);

        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');
        $response = $middleware->handle($request, fn () => response('should-not-run', 200), 'api');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertSame(url('/'), $response->headers->get('Location'));
    }
}
