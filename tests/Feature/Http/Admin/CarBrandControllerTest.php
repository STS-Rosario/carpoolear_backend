<?php

namespace Tests\Feature\Http\Admin;

use STS\Http\Middleware\UserAdmin;
use STS\Models\Car;
use STS\Models\CarBrand;
use STS\Models\User;
use Tests\TestCase;

class CarBrandControllerTest extends TestCase
{
    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->actingAs($admin->fresh(), 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_admin_lists_brands(): void
    {
        $this->authenticateAdmin();
        CarBrand::factory()->create(['name' => 'Toyota']);

        $response = $this->getJson('api/admin/car-brands')->assertOk();

        $this->assertTrue(
            collect($response->json('data'))->contains(fn (array $row) => $row['name'] === 'Toyota')
        );
    }

    public function test_admin_stores_brand(): void
    {
        $this->authenticateAdmin();

        $response = $this->postJson('api/admin/car-brands', [
            'name' => 'Ford',
        ])->assertCreated();

        $this->assertSame('Ford', $response->json('data.name'));
        $this->assertSame('ford', $response->json('data.slug'));
    }

    public function test_destroy_deactivates_brand_when_referenced_by_car(): void
    {
        $this->authenticateAdmin();
        $user = User::factory()->create();
        $car = Car::factory()->withCatalog()->create(['user_id' => $user->id]);
        $brand = CarBrand::query()->findOrFail($car->car_brand_id);

        $this->deleteJson("api/admin/car-brands/{$brand->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($brand->fresh()->is_active);
        $this->assertNotNull(CarBrand::query()->find($brand->id));
    }
}
