<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionProperty;
use STS\Http\Middleware\CheckUserBanned;
use STS\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class CheckUserBannedTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_testing_environment_skips_banned_check_and_continues(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $jwt = Mockery::mock(JWTAuth::class);
        $middleware = new CheckUserBanned($jwt);
        $this->assertNull($this->readAuthProperty($middleware));

        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('through', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('through', $response->getContent());
    }

    public function test_no_token_continues_without_authenticate(): void
    {
        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->once()->andReturn(false);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->once()->andReturn($parser);
        $jwt->shouldNotReceive('parseToken');

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_non_banned_user_continues(): void
    {
        $user = User::factory()->create([
            'banned' => false,
            'active' => true,
        ]);

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('allowed'));

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_banned_user_aborts_with_403(): void
    {
        $this->assertBannedRequestIsDenied('GET', '/api/trips');
    }

    public function test_banned_user_is_allowed_to_list_support_tickets(): void
    {
        $this->assertBannedRequestIsAllowed('GET', '/api/support/tickets');
    }

    public function test_banned_user_is_blocked_from_creating_support_tickets(): void
    {
        $this->assertBannedRequestIsDenied('POST', '/api/support/tickets');
    }

    public function test_banned_user_is_allowed_to_view_and_reply_to_support_tickets(): void
    {
        $this->assertBannedRequestIsAllowed('GET', '/api/support/tickets/12');
        $this->assertBannedRequestIsAllowed('POST', '/api/support/tickets/12/replies');
        $this->assertBannedRequestIsAllowed('POST', '/api/support/tickets/12/close');
        $this->assertBannedRequestIsAllowed('GET', '/api/support/tickets/12/attachments/3/image');
    }

    public function test_banned_user_is_allowed_to_load_own_profile_and_refresh_session(): void
    {
        $this->assertBannedRequestIsAllowed('GET', '/api/users/me');
        $this->assertBannedRequestIsAllowed('POST', '/api/retoken');
        $this->assertBannedRequestIsAllowed('POST', '/api/logout');
    }

    public function test_null_user_from_authenticate_continues(): void
    {
        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn(null);

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('null-user-ok'));

        $this->assertSame('null-user-ok', $response->getContent());
    }

    public function test_banned_session_user_without_token_is_restricted_by_allowlist(): void
    {
        $bannedUser = User::factory()->create([
            'banned' => true,
            'active' => true,
        ]);
        $this->actingAs($bannedUser, 'api');

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(false);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldNotReceive('parseToken');

        $middleware = $this->middlewareWithInjectedAuth($jwt);

        $allowed = $middleware->handle(
            Request::create('/api/support/tickets', 'GET'),
            fn () => response('tickets-ok')
        );
        $this->assertSame('tickets-ok', $allowed->getContent());

        try {
            $middleware->handle(Request::create('/api/trips', 'GET'), fn () => response('should-not-run'));
            $this->fail('Expected HttpException 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('Access denied', $e->getMessage());
        }
    }

    public function test_authenticate_exception_is_swallowed_and_request_continues(): void
    {
        Log::spy();

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andThrow(new \RuntimeException('bad token'));

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('recovered'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('CheckUserBanned middleware error: bad token');
        $this->assertSame('recovered', $response->getContent());
    }

    private function readAuthProperty(CheckUserBanned $middleware): mixed
    {
        $prop = new ReflectionProperty(CheckUserBanned::class, 'auth');
        $prop->setAccessible(true);

        return $prop->getValue($middleware);
    }

    private function middlewareWithInjectedAuth(JWTAuth $jwt): CheckUserBanned
    {
        $middleware = new CheckUserBanned($jwt);
        $prop = new ReflectionProperty(CheckUserBanned::class, 'auth');
        $prop->setAccessible(true);
        $prop->setValue($middleware, $jwt);

        return $middleware;
    }

    private function assertBannedRequestIsAllowed(string $method, string $uri): void
    {
        $response = $this->handleBannedRequest($method, $uri, fn () => response('allowed'));

        $this->assertSame('allowed', $response->getContent());
    }

    private function assertBannedRequestIsDenied(string $method, string $uri): void
    {
        try {
            $this->handleBannedRequest($method, $uri, fn () => response('should-not-run'));
            $this->fail('Expected HttpException 403 for '.$method.' '.$uri);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('Access denied', $e->getMessage());
        }
    }

    private function handleBannedRequest(string $method, string $uri, callable $next)
    {
        $user = User::factory()->create([
            'banned' => true,
            'active' => true,
        ]);

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = $this->middlewareWithInjectedAuth($jwt);

        return $middleware->handle(Request::create($uri, $method), $next);
    }
}
