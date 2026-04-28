<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
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

    /**
     * In `testing`, the constructor intentionally does not assign {@see CheckUserBanned::$auth},
     * so JWT parsing is skipped and the pipeline always continues.
     */
    public function test_testing_environment_skips_banned_check_and_continues(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $jwt = Mockery::mock(JWTAuth::class);
        $middleware = new CheckUserBanned($jwt);

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

        try {
            $middleware->handle(Request::create('/', 'GET'), fn () => response('should-not-run'));
            $this->fail('Expected HttpException 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('Access denied', $e->getMessage());
        }
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

    public function test_banned_session_user_without_token_is_not_blocked_by_this_middleware(): void
    {
        $bannedUser = User::factory()->create([
            'banned' => true,
            'active' => true,
        ]);
        $this->actingAs($bannedUser, 'api');

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->once()->andReturn(false);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->once()->andReturn($parser);
        $jwt->shouldNotReceive('parseToken');

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('no-token-path'));

        $this->assertSame('no-token-path', $response->getContent());
    }

    public function test_authenticate_exception_is_swallowed_and_request_continues(): void
    {
        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andThrow(new \RuntimeException('bad token'));

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('recovered'));

        $this->assertSame('recovered', $response->getContent());
    }

    /**
     * Mirror production wiring: {@see CheckUserBanned} only assigns $auth outside `testing`.
     */
    private function middlewareWithInjectedAuth(JWTAuth $jwt): CheckUserBanned
    {
        $middleware = new CheckUserBanned($jwt);
        $prop = new ReflectionProperty(CheckUserBanned::class, 'auth');
        $prop->setAccessible(true);
        $prop->setValue($middleware, $jwt);

        return $middleware;
    }
}
