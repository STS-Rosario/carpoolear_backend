<?php

namespace Tests\Feature\Http\Admin;

use STS\Http\Middleware\UserAdmin;
use STS\Models\CarBrand;
use STS\Models\CarModel;
use STS\Models\User;
use Tests\TestCase;

class CarModelControllerTest extends TestCase
{
    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->actingAs($admin->fresh(), 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_admin_lists_models_for_brand(): void
    {
        $this->authenticateAdmin();
        $brand = CarBrand::factory()->create();
        CarModel::factory()->create(['car_brand_id' => $brand->id, 'name' => 'Corolla']);

        $response = $this->getJson("api/admin/car-brands/{$brand->id}/models")->assertOk();

        $this->assertTrue(
            collect($response->json('data'))->contains(fn (array $row) => $row['name'] === 'Corolla')
        );
    }

    public function test_admin_stores_model_for_brand(): void
    {
        $this->authenticateAdmin();
        $brand = CarBrand::factory()->create(['name' => 'Toyota']);

        $response = $this->postJson("api/admin/car-brands/{$brand->id}/models", [
            'name' => 'Hilux',
        ])->assertCreated();

        $this->assertSame('Hilux', $response->json('data.name'));
        $this->assertSame($brand->id, $response->json('data.car_brand_id'));
    }
}
