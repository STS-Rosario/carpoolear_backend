<?php

namespace Tests\Feature\Http\Admin;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use STS\Http\Middleware\UserAdmin;
use STS\Jobs\SyncArgautosCarCatalogJob;
use STS\Models\User;
use STS\Services\Argautos\CarCatalogSyncService;
use Tests\TestCase;

class CarCatalogSyncControllerTest extends TestCase
{
    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->actingAs($admin->fresh(), 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_admin_can_queue_sync_job(): void
    {
        Bus::fake();
        Cache::forget(CarCatalogSyncService::STATUS_CACHE_KEY);
        $this->authenticateAdmin();

        $this->postJson('api/admin/car-catalog/sync')
            ->assertAccepted()
            ->assertJsonPath('data.queued', true);

        Bus::assertDispatched(SyncArgautosCarCatalogJob::class);
    }

    public function test_admin_sync_returns_conflict_when_running(): void
    {
        Bus::fake();
        Cache::put(CarCatalogSyncService::STATUS_CACHE_KEY, [
            'running' => true,
            'last_run' => null,
        ]);
        $this->authenticateAdmin();

        $this->postJson('api/admin/car-catalog/sync')
            ->assertStatus(409);

        Bus::assertNotDispatched(SyncArgautosCarCatalogJob::class);
    }

    public function test_admin_can_read_sync_status(): void
    {
        Cache::put(CarCatalogSyncService::STATUS_CACHE_KEY, [
            'running' => false,
            'last_run' => ['models_created' => 3],
        ]);
        $this->authenticateAdmin();

        $this->getJson('api/admin/car-catalog/sync-status')
            ->assertOk()
            ->assertJsonPath('data.last_run.models_created', 3);
    }
}
