<?php

namespace Tests\Feature\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;
use STS\Models\User;
use STS\Services\Logic\NotificationManager;
use STS\Services\Maintenance\MaintenanceStateService;
use Tests\TestCase;

class MaintenanceMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->mock(NotificationManager::class, function ($mock): void {
            $mock->shouldReceive('getNotifications')->byDefault()->andReturn([]);
            $mock->shouldReceive('getUnreadCount')->byDefault()->andReturn(0);
            $mock->shouldReceive('delete')->byDefault()->andReturn(true);
        });
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user->fresh();
    }

    public function test_strict_returns_503_for_logged_route_and_leaves_config_public(): void
    {
        app(MaintenanceStateService::class)->applyManualActive(true, 'strict', ' outage ', null, 'manual', null, null);

        $user = User::factory()->create();
        $this->actingAsApiUser($user);

        $this->getJson('api/notifications')
            ->assertStatus(503)
            ->assertJson([
                'maintenance' => true,
                'enabled' => true,
                'mode' => 'strict',
                'message' => ' outage ',
            ]);

        $this->getJson('api/config')->assertOk()->assertJsonPath('maintenance.enabled', true);

        app(MaintenanceStateService::class)->applyManualActive(false, null, null, null, 'manual', null, null);
    }

    public function test_flexible_allows_admin_through_logged_route(): void
    {
        app(MaintenanceStateService::class)->applyManualActive(true, 'flexible', 'warn', null, 'manual', null, null);

        $admin = $this->makeAdmin();
        $this->actingAsApiUser($admin);

        $this->getJson('api/notifications')->assertOk();

        app(MaintenanceStateService::class)->applyManualActive(false, null, null, null, 'manual', null, null);
    }

    public function test_flexible_blocks_non_admin_on_logged_route(): void
    {
        app(MaintenanceStateService::class)->applyManualActive(true, 'flexible', 'warn', null, 'manual', null, null);

        $user = User::factory()->create();
        $this->actingAsApiUser($user);

        $this->getJson('api/notifications')->assertStatus(503);

        app(MaintenanceStateService::class)->applyManualActive(false, null, null, null, 'manual', null, null);
    }

    public function test_strict_blocks_admin_on_logged_route(): void
    {
        app(MaintenanceStateService::class)->applyManualActive(true, 'strict', 'full', null, 'manual', null, null);

        $admin = $this->makeAdmin();
        $this->actingAsApiUser($admin);

        $this->getJson('api/notifications')->assertStatus(503);

        app(MaintenanceStateService::class)->applyManualActive(false, null, null, null, 'manual', null, null);
    }
}
