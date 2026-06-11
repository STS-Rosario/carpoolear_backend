<?php

namespace Tests\Unit\Services\Argautos;

use Illuminate\Support\Facades\Http;
use STS\Models\CarBrand;
use STS\Models\CarModel;
use STS\Services\Argautos\CarCatalogSyncService;
use Tests\TestCase;

class CarCatalogSyncServiceTest extends TestCase
{
    public function test_should_import_model_name_filters_versions(): void
    {
        $service = new CarCatalogSyncService;

        $this->assertFalse($service->shouldImportModelName('4P 1,4 TDCI MAX'));
        $this->assertFalse($service->shouldImportModelName('FIESTA TDCI'));
        $this->assertFalse($service->shouldImportModelName('FOCUS AT'));
        $this->assertFalse($service->shouldImportModelName('COROLLA CVT'));
        $this->assertTrue($service->shouldImportModelName('COROLLA'));
        $this->assertTrue($service->shouldImportModelName('HILUX PICK - UP'));
    }

    public function test_sync_incremental_creates_new_brands_and_models(): void
    {
        $brandArgautosId = 9_000_001;
        $modelArgautosId = 9_000_002;
        $skippedModelArgautosId = 9_000_003;

        Http::fake(function ($request) use ($brandArgautosId, $modelArgautosId, $skippedModelArgautosId) {
            if (str_contains($request->url(), "/brands/{$brandArgautosId}/models")) {
                return Http::response([
                    'data' => [
                        ['id' => $modelArgautosId, 'brand_id' => $brandArgautosId, 'name' => 'SYNC TEST MODEL', 'slug' => 'sync-test-model'],
                        ['id' => $skippedModelArgautosId, 'brand_id' => $brandArgautosId, 'name' => '4P 1,5 XLS 4AT 2022', 'slug' => 'version'],
                    ],
                    'links' => ['next' => null],
                ], 200);
            }

            return Http::response([
                'data' => [
                    ['id' => $brandArgautosId, 'name' => 'SYNC TEST BRAND', 'slug' => 'sync-test-brand'],
                ],
                'links' => ['next' => null],
            ], 200);
        });

        $service = new CarCatalogSyncService('https://argautos.test/api/v1', null, 0);
        $summary = $service->sync('incremental', false);

        $this->assertSame(1, $summary['brands_created']);
        $this->assertSame(1, $summary['models_created']);
        $this->assertSame(1, $summary['models_skipped']);
        $this->assertDatabaseHas('car_brands', ['argautos_id' => $brandArgautosId, 'name' => 'SYNC TEST BRAND']);
        $this->assertDatabaseHas('car_models', ['argautos_id' => $modelArgautosId, 'name' => 'SYNC TEST MODEL']);
        $this->assertDatabaseMissing('car_models', ['argautos_id' => $skippedModelArgautosId]);
    }

    public function test_sync_incremental_skips_existing_argautos_ids(): void
    {
        $brandArgautosId = 9_000_010;
        $existingModelArgautosId = 9_000_011;
        $newModelArgautosId = 9_000_012;

        $brand = CarBrand::factory()->create(['argautos_id' => $brandArgautosId, 'name' => 'SYNC SKIP BRAND']);
        CarModel::factory()->create([
            'car_brand_id' => $brand->id,
            'argautos_id' => $existingModelArgautosId,
            'name' => 'EXISTING MODEL',
        ]);

        Http::fake(function ($request) use ($brandArgautosId, $existingModelArgautosId, $newModelArgautosId) {
            if (str_contains($request->url(), "/brands/{$brandArgautosId}/models")) {
                return Http::response([
                    'data' => [
                        ['id' => $existingModelArgautosId, 'brand_id' => $brandArgautosId, 'name' => 'EXISTING MODEL', 'slug' => 'existing-model'],
                        ['id' => $newModelArgautosId, 'brand_id' => $brandArgautosId, 'name' => 'NEW MODEL', 'slug' => 'new-model'],
                    ],
                    'links' => ['next' => null],
                ], 200);
            }

            return Http::response([
                'data' => [
                    ['id' => $brandArgautosId, 'name' => 'SYNC SKIP BRAND', 'slug' => 'sync-skip-brand'],
                ],
                'links' => ['next' => null],
            ], 200);
        });

        $service = new CarCatalogSyncService('https://argautos.test/api/v1', null, 0);
        $summary = $service->sync('incremental', false);

        $this->assertSame(0, $summary['brands_created']);
        $this->assertSame(1, $summary['models_created']);
        $this->assertDatabaseHas('car_models', ['argautos_id' => $newModelArgautosId, 'name' => 'NEW MODEL']);
    }
}
