<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mockery;
use STS\Http\Middleware\UserAdmin;
use STS\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class UserAdminTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function userWithAdminFlag(bool $isAdmin): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => $isAdmin])->saveQuietly();

        return $user->fresh();
    }

    public function test_admin_user_is_allowed_through(): void
    {
        $user = $this->userWithAdminFlag(true);
        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserAdmin($jwt);
        $response = $middleware->handle(Request::create('/api/admin/badges', 'GET'), fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_non_admin_receives_401_json(): void
    {
        $user = $this->userWithAdminFlag(false);
        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserAdmin($jwt);
        $response = $middleware->handle(Request::create('/api/admin/badges', 'GET'), fn () => response('no'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Unauthorized.', json_decode($response->getContent(), true));
    }

    public function test_null_user_from_authenticate_receives_401(): void
    {
        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn(null);

        $middleware = new UserAdmin($jwt);
        $response = $middleware->handle(Request::create('/api/admin/badges', 'GET'), fn () => response('no'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_constructor_rethrows_when_jwt_authenticate_fails(): void
    {
        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andThrow(new \RuntimeException('invalid token'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid token');

        new UserAdmin($jwt);
    }
}
