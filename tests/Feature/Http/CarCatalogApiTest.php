<?php

namespace Tests\Feature\Http;

use STS\Models\CarBrand;
use STS\Models\CarColor;
use STS\Models\CarModel;
use Tests\TestCase;

class CarCatalogApiTest extends TestCase
{
    public function test_lists_active_brands_without_auth(): void
    {
        CarBrand::factory()->create(['name' => 'Toyota', 'is_active' => true]);
        CarBrand::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $response = $this->getJson('api/car-brands')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Toyota', $names);
        $this->assertNotContains('Hidden', $names);
    }

    public function test_lists_active_models_for_brand_without_hex_on_colors(): void
    {
        $brand = CarBrand::factory()->create();
        CarModel::factory()->create(['car_brand_id' => $brand->id, 'name' => 'Corolla', 'is_active' => true]);
        CarModel::factory()->create(['car_brand_id' => $brand->id, 'name' => 'Hidden', 'is_active' => false]);

        $models = $this->getJson("api/car-brands/{$brand->id}/models")
            ->assertOk()
            ->json('data');

        $this->assertSame(['Corolla'], collect($models)->pluck('name')->all());
    }

    public function test_lists_active_colors_without_hex(): void
    {
        CarColor::factory()->create(['name' => 'Blanco', 'hex' => '#FFFFFF', 'sort_order' => 1]);
        CarColor::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $row = $this->getJson('api/car-colors')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Blanco', $row['name']);
        $this->assertArrayNotHasKey('hex', $row);
    }
}
