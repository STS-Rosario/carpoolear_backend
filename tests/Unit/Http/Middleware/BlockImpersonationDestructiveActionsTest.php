<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Mockery;
use STS\Http\Middleware\BlockImpersonationDestructiveActions;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Payload;

class BlockImpersonationDestructiveActionsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_blocks_delete_account_when_impersonating(): void
    {
        $middleware = $this->middlewareWithImpClaim(true);

        $request = Request::create('/api/users/delete-account', 'POST');
        $request->setRouteResolver(function () use ($request) {
            $route = new \Illuminate\Routing\Route('POST', 'api/users/delete-account', []);
            $route->bind($request);

            return $route;
        });

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('impersonation_action_forbidden', $response->getData(true)['message']);
    }

    public function test_allows_delete_account_without_impersonation_claim(): void
    {
        $middleware = $this->middlewareWithImpClaim(false);

        $request = Request::create('/api/users/delete-account', 'POST');
        $request->setRouteResolver(function () use ($request) {
            $route = new \Illuminate\Routing\Route('POST', 'api/users/delete-account', []);
            $route->bind($request);

            return $route;
        });

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_allows_safe_routes_while_impersonating(): void
    {
        $middleware = $this->middlewareWithImpClaim(true);

        $request = Request::create('/api/users/me', 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = new \Illuminate\Routing\Route('GET', 'api/users/me', []);
            $route->bind($request);

            return $route;
        });

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    private function middlewareWithImpClaim(bool $impersonating): BlockImpersonationDestructiveActions
    {
        $payload = Mockery::mock(Payload::class);
        $payload->shouldReceive('get')
            ->with('imp')
            ->andReturn($impersonating ? true : null);

        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken')->andReturnSelf();
        $jwt->shouldReceive('getPayload')->andReturn($payload);

        return new BlockImpersonationDestructiveActions($jwt);
    }
}
