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

    public function test_car_brand_factory_does_not_assign_catalog_range_argautos_ids(): void
    {
        // Catalog seeds use low argautos_ids (including 70). Factory must not invent
        // colliding IDs — Faker unique() does not know about rows already in the DB.
        $definition = (new \Database\Factories\CarBrandFactory)->definition();

        $this->assertNull($definition['argautos_id']);
    }

    public function test_car_brand_factory_creates_without_colliding_with_seeded_catalog(): void
    {
        // Suite may already have seeded catalog brands; factory creates must not throw.
        $created = CarBrand::factory()->count(10)->create();

        $this->assertCount(10, $created);
        $this->assertTrue($created->every(fn (CarBrand $brand) => $brand->argautos_id === null));
    }
}
