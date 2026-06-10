<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use STS\Services\Argautos\CarCatalogSyncService;
use Tests\TestCase;

class SyncArgautosCarCatalogTest extends TestCase
{
    public function test_command_runs_incremental_sync(): void
    {
        config([
            'carpoolear.argautos_api_base_url' => 'https://argautos.test/api/v1',
            'carpoolear.argautos_request_delay_ms' => 0,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/brands/19/models')) {
                return Http::response([
                    'data' => [['id' => 227, 'brand_id' => 19, 'name' => 'FIESTA', 'slug' => 'fiesta']],
                    'links' => ['next' => null],
                ], 200);
            }

            return Http::response([
                'data' => [['id' => 19, 'name' => 'FORD', 'slug' => 'ford']],
                'links' => ['next' => null],
            ], 200);
        });

        Cache::lock(CarCatalogSyncService::LOCK_KEY)->forceRelease();

        $this->artisan('car-catalog:sync-argautos', ['--mode' => 'incremental'])
            ->assertSuccessful();

        $this->assertDatabaseHas('car_brands', ['argautos_id' => 19, 'name' => 'FORD']);
        $this->assertDatabaseHas('car_models', ['argautos_id' => 227, 'name' => 'FIESTA']);
    }
}
