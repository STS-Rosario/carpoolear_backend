<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use STS\Http\Middleware\Authenticate;
use STS\Models\User;
use Tests\TestCase;

class AuthenticateTest extends TestCase
{
    public function test_guest_with_json_accept_receives_401(): void
    {
        $this->assertGuest('web');

        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $middleware = new Authenticate;
        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Unauthorized.', $response->getContent());
    }

    public function test_guest_with_ajax_header_receives_401(): void
    {
        $this->assertGuest('web');

        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $middleware = new Authenticate;
        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Unauthorized.', $response->getContent());
    }

    public function test_guest_html_request_redirects_to_login(): void
    {
        $this->assertGuest('web');

        $request = Request::create('/dashboard', 'GET');

        $middleware = new Authenticate;
        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('login', (string) $response->headers->get('Location'));
    }

    public function test_authenticated_user_passes_through(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);

        $request = Request::create('/dashboard', 'GET');

        $middleware = new Authenticate;
        $ran = false;
        $response = $middleware->handle($request, function ($req) use (&$ran) {
            $ran = true;

            return response('through', 200);
        }, 'web');

        $this->assertTrue($ran);
        $this->assertSame('through', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_explicit_guard_is_respected_for_guest(): void
    {
        $this->assertGuest('web');

        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $middleware = new Authenticate;
        $response = $middleware->handle($request, fn () => response('next'), 'web');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_default_guard_allows_request_when_web_user_is_authenticated(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);

        $request = Request::create('/dashboard', 'GET');
        $middleware = new Authenticate;

        $response = $middleware->handle($request, fn () => response('ok-default', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok-default', $response->getContent());
    }

    public function test_authenticated_user_on_web_still_fails_when_api_guard_requested(): void
    {
        $user = User::factory()->create();
        Auth::guard('web')->login($user);
        $this->assertGuest('api');

        $request = Request::create('/dashboard', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $middleware = new Authenticate;
        $response = $middleware->handle($request, fn () => response('next'), 'api');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Unauthorized.', $response->getContent());
    }
}
