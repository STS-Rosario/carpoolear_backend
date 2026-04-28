<?php

namespace Tests\Unit\Http\Middleware;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Mockery;
use ReflectionProperty;
use STS\Http\Middleware\UpdateConnection;
use STS\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class UpdateConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_testing_environment_skips_jwt_and_always_continues(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $jwt = Mockery::mock(JWTAuth::class);
        $middleware = new UpdateConnection($jwt);

        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('through', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('through', $response->getContent());
    }

    public function test_no_token_does_not_touch_user_or_call_authenticate(): void
    {
        $user = User::factory()->create();
        $before = $user->fresh()->last_connection?->copy();

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->once()->andReturn(false);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->once()->andReturn($parser);
        $jwt->shouldNotReceive('parseToken');

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $middleware->handle(Request::create('/', 'GET'), fn () => response('ok'));

        $this->assertTrue($user->fresh()->last_connection->equalTo($before));
    }

    public function test_authenticated_user_last_connection_is_updated(): void
    {
        $past = Carbon::now()->subDays(5);
        $user = User::factory()->create();
        $user->forceFill(['last_connection' => $past])->saveQuietly();
        $user = $user->fresh();

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $middleware->handle(Request::create('/', 'GET'), fn () => response('done'));

        $user->refresh();
        $this->assertTrue($user->last_connection->greaterThan($past));
        $this->assertLessThanOrEqual(
            5,
            abs((int) $user->last_connection->diffInSeconds(Carbon::now())),
            'last_connection should be refreshed to near the current time'
        );
    }

    public function test_authenticated_user_with_null_last_connection_gets_timestamp_set(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['last_connection' => null])->saveQuietly();

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('ok-null'));

        $this->assertSame('ok-null', $response->getContent());
        $this->assertNotNull($user->fresh()->last_connection);
    }

    public function test_null_authenticated_user_does_not_save(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['last_connection' => Carbon::parse('2020-01-01 12:00:00')])->saveQuietly();
        $frozen = $user->fresh()->last_connection->copy();

        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn(null);

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $middleware->handle(Request::create('/', 'GET'), fn () => response('x'));

        $this->assertTrue($user->fresh()->last_connection->equalTo($frozen));
    }

    public function test_authenticate_exception_is_swallowed_and_pipeline_continues(): void
    {
        $parser = Mockery::mock();
        $parser->shouldReceive('hasToken')->andReturn(true);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parser')->andReturn($parser);
        $jwt->shouldReceive('parseToken->authenticate')->andThrow(new \RuntimeException('token bad'));

        $middleware = $this->middlewareWithInjectedAuth($jwt);
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('recovered'));

        $this->assertSame('recovered', $response->getContent());
    }

    private function middlewareWithInjectedAuth(JWTAuth $jwt): UpdateConnection
    {
        $middleware = new UpdateConnection($jwt);
        $prop = new ReflectionProperty(UpdateConnection::class, 'auth');
        $prop->setAccessible(true);
        $prop->setValue($middleware, $jwt);

        return $middleware;
    }
}
