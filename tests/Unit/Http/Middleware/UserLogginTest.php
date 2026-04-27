<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mockery;
use STS\Http\Middleware\UserLoggin;
use STS\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class UserLogginTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_valid_jwt_user_proceeds_and_sets_auth(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => false,
            'active' => true,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(Request::create('https://api.example/trips', 'GET'), function () use ($user) {
            $this->assertTrue(auth()->check());
            $this->assertTrue(auth()->user()->is($user));

            return response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_jwt_failure_falls_back_to_acting_as_user(): void
    {
        $user = User::factory()->create([
            'banned' => false,
            'active' => true,
        ]);
        $this->actingAs($user, 'api');

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andThrow(new \RuntimeException('Token invalid'));

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(Request::create('https://api.example/trips', 'GET'), function () use ($user) {
            $this->assertTrue(auth()->user()->is($user));

            return response('via-fallback', 200);
        });

        $this->assertSame('via-fallback', $response->getContent());
    }

    public function test_guest_without_token_receives_401_json(): void
    {
        $this->assertGuest();

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andThrow(new \RuntimeException('missing'));

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(Request::create('https://api.example/protected', 'GET'), fn () => response('no', 200));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Unauthorized.'], $response->getData(true));
    }

    public function test_banned_user_receives_401(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => true,
            'active' => true,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('no'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_inactive_user_receives_401(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => false,
            'active' => false,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('no'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_optional_mode_sets_user_and_always_continues(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => false,
            'active' => true,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(
            Request::create('/', 'GET'),
            function () use ($user) {
                $this->assertTrue(auth()->user()->is($user));

                return response('optional-ok');
            },
            'optional'
        );

        $this->assertSame('optional-ok', $response->getContent());
    }

    public function test_optional_mode_allows_banned_user_through(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => true,
            'active' => true,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserLoggin($jwt);
        $response = $middleware->handle(
            Request::create('/', 'GET'),
            function () use ($user) {
                $this->assertTrue(auth()->user()->is($user));

                return response('optional-banned');
            },
            'optional'
        );

        $this->assertSame('optional-banned', $response->getContent());
    }
}
