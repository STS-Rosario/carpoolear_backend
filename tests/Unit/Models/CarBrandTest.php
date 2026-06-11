<?php

namespace Tests\Unit\Models;

use STS\Models\CarBrand;
use STS\Models\CarModel;
use Tests\TestCase;

class CarBrandTest extends TestCase
{
    public function test_car_model_belongs_to_brand(): void
    {
        $brand = CarBrand::factory()->create(['name' => 'Toyota']);
        $model = CarModel::factory()->create([
            'car_brand_id' => $brand->id,
            'name' => 'Corolla',
        ]);

        $this->assertTrue($model->fresh()->brand->is($brand));
    }

    public function test_active_scope_returns_only_active_brands(): void
    {
        CarBrand::factory()->create(['name' => 'ActiveScopeBrand', 'is_active' => true]);
        CarBrand::factory()->create(['name' => 'InactiveScopeBrand', 'is_active' => false]);

        $activeNames = CarBrand::active()->pluck('name')->all();

        $this->assertContains('ActiveScopeBrand', $activeNames);
        $this->assertNotContains('InactiveScopeBrand', $activeNames);
    }

    public function test_brand_has_many_models(): void
    {
        $brand = CarBrand::factory()->create();
        CarModel::factory()->count(2)->create(['car_brand_id' => $brand->id]);

        $this->assertCount(2, $brand->fresh()->models);
    }
}
