<?php

namespace Tests\Unit\Coverage;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Events\Passenger\AutoCancel;
use STS\Events\Passenger\AutoRequest;
use STS\Events\TestEvent;
use STS\Events\User\Reset;
use STS\Http\Controllers\Api\v1\DebugController;
use STS\Http\Middleware\RedirectIfAuthenticated;
use STS\Http\Middleware\UserAdmin;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\JWTAuth;

class UncoveredInfrastructureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_debug_controller_log_swallows_empty_request(): void
    {
        $controller = new DebugController;
        $request = Request::create('/api/log', 'POST', []);
        $controller->log($request);
        $this->assertTrue(true);
    }

    public function test_debug_controller_log_with_payload(): void
    {
        $controller = new DebugController;
        $request = Request::create('/api/log', 'POST', ['log' => 'unit-test']);
        $controller->log($request);
        $this->assertTrue(true);
    }

    public function test_events_can_be_constructed_and_dispatched(): void
    {
        Event::fake();

        event(new TestEvent);
        event(new Reset(1, 'token-string'));

        $trip = Trip::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();
        event(new AutoCancel($trip, $from, $to));
        event(new AutoRequest($trip, $from, $to));

        Event::assertDispatched(TestEvent::class);
        Event::assertDispatched(Reset::class);
        Event::assertDispatched(AutoCancel::class);
        Event::assertDispatched(AutoRequest::class);
    }

    public function test_redirect_if_authenticated_middleware_passes_when_guest(): void
    {
        $middleware = new RedirectIfAuthenticated;
        $request = Request::create('/login', 'GET');
        $called = false;
        $next = function () use (&$called) {
            $called = true;

            return response('next');
        };

        $response = $middleware->handle($request, $next, 'web');
        $this->assertTrue($called);
        $this->assertSame('next', $response->getContent());
    }

    public function test_user_admin_middleware_allows_admin_user(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserAdmin($jwt);
        $request = Request::create('/admin', 'GET');
        $response = $middleware->handle($request, fn () => response('admin-ok'));

        $this->assertSame('admin-ok', $response->getContent());
    }

    public function test_user_admin_middleware_blocks_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $jwt = Mockery::mock(JWTAuth::class);
        $jwt->shouldReceive('parseToken->authenticate')->andReturn($user);

        $middleware = new UserAdmin($jwt);
        $request = Request::create('/admin', 'GET');
        $response = $middleware->handle($request, fn () => response('should-not-run'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_badge_assignment_command_dry_run_exits_zero(): void
    {
        $code = Artisan::call('badges:assign-msmjms-campaign', ['--dry-run' => true]);
        $this->assertSame(0, $code);
    }

    public function test_cleanup_duplicate_cars_dry_run_exits_zero(): void
    {
        $code = Artisan::call('cars:cleanup-duplicates', ['--dry-run' => true]);
        $this->assertSame(0, $code);
    }

    public function test_evaluate_badges_dry_run_exits_zero(): void
    {
        $code = Artisan::call('badges:evaluate', [
            '--dry-run' => true,
            '--batch-size' => 1,
        ]);
        $this->assertSame(0, $code);
    }
}
