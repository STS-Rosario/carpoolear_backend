<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mockery;
use STS\Http\Middleware\AuthOptional;
use STS\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class AuthOptionalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_invalid_token_still_invokes_next_and_leaves_guest(): void
    {
        $this->assertGuest();

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')
            ->andThrow(new \RuntimeException('Token absent'));

        $middleware = new AuthOptional($jwt);
        $ran = false;
        $response = $middleware->handle(Request::create('/trips', 'GET'), function () use (&$ran) {
            $ran = true;

            return response('body', 200);
        });

        $this->assertTrue($ran);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertGuest();
    }

    public function test_null_user_still_invokes_next(): void
    {
        $this->assertGuest();

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn(null);

        $middleware = new AuthOptional($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertGuest();
    }

    public function test_active_user_sets_default_auth_and_continues(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => false,
            'active' => true,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new AuthOptional($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), function () use ($user) {
            $this->assertTrue(auth()->check());
            $this->assertTrue(auth()->user()->is($user));

            return response('in');
        });

        $this->assertSame('in', $response->getContent());
    }

    public function test_banned_user_does_not_set_auth(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => true,
            'active' => true,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new AuthOptional($jwt);
        $middleware->handle(Request::create('/', 'GET'), fn () => response('done'));

        $this->assertGuest();
    }

    public function test_inactive_user_does_not_set_auth(): void
    {
        $this->assertGuest();

        $user = User::factory()->create([
            'banned' => false,
            'active' => false,
        ]);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new AuthOptional($jwt);
        $middleware->handle(Request::create('/', 'GET'), fn () => response('done'));

        $this->assertGuest();
    }
}
