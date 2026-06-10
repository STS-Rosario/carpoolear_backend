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
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/brands/60/models')) {
                return Http::response([
                    'data' => [
                        ['id' => 563, 'brand_id' => 60, 'name' => 'COROLLA', 'slug' => 'corolla'],
                        ['id' => 643, 'brand_id' => 60, 'name' => '4P 1,5 XLS 4AT 2022', 'slug' => 'version'],
                    ],
                    'links' => ['next' => null],
                ], 200);
            }

            return Http::response([
                'data' => [
                    ['id' => 60, 'name' => 'TOYOTA', 'slug' => 'toyota'],
                ],
                'links' => ['next' => null],
            ], 200);
        });

        $service = new CarCatalogSyncService('https://argautos.test/api/v1', null, 0);
        $summary = $service->sync('incremental', false);

        $this->assertSame(1, $summary['brands_created']);
        $this->assertSame(1, $summary['models_created']);
        $this->assertSame(1, $summary['models_skipped']);
        $this->assertDatabaseHas('car_brands', ['argautos_id' => 60, 'name' => 'TOYOTA']);
        $this->assertDatabaseHas('car_models', ['argautos_id' => 563, 'name' => 'COROLLA']);
        $this->assertDatabaseMissing('car_models', ['argautos_id' => 643]);
    }

    public function test_sync_incremental_skips_existing_argautos_ids(): void
    {
        $brand = CarBrand::factory()->create(['argautos_id' => 60, 'name' => 'TOYOTA']);
        CarModel::factory()->create([
            'car_brand_id' => $brand->id,
            'argautos_id' => 563,
            'name' => 'COROLLA',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/brands/60/models')) {
                return Http::response([
                    'data' => [
                        ['id' => 563, 'brand_id' => 60, 'name' => 'COROLLA', 'slug' => 'corolla'],
                        ['id' => 564, 'brand_id' => 60, 'name' => 'YARIS', 'slug' => 'yaris'],
                    ],
                    'links' => ['next' => null],
                ], 200);
            }

            return Http::response([
                'data' => [
                    ['id' => 60, 'name' => 'TOYOTA', 'slug' => 'toyota'],
                ],
                'links' => ['next' => null],
            ], 200);
        });

        $service = new CarCatalogSyncService('https://argautos.test/api/v1', null, 0);
        $summary = $service->sync('incremental', false);

        $this->assertSame(0, $summary['brands_created']);
        $this->assertSame(1, $summary['models_created']);
        $this->assertDatabaseHas('car_models', ['argautos_id' => 564, 'name' => 'YARIS']);
    }
}
