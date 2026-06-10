<?php

namespace Tests\Feature\Http\Admin;

use STS\Http\Middleware\UserAdmin;
use STS\Models\CarColor;
use STS\Models\User;
use Tests\TestCase;

class CarColorControllerTest extends TestCase
{
    private function authenticateAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->actingAs($admin->fresh(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        return $admin;
    }

    public function test_admin_lists_colors_with_hex(): void
    {
        $this->authenticateAdmin();
        CarColor::factory()->create(['name' => 'Blanco', 'hex' => '#FFFFFF']);

        $response = $this->getJson('api/admin/car-colors')->assertOk();
        $row = collect($response->json('data'))->first();

        $this->assertSame('Blanco', $row['name']);
        $this->assertSame('#FFFFFF', $row['hex']);
    }

    public function test_admin_stores_color_with_valid_hex(): void
    {
        $this->authenticateAdmin();

        $response = $this->postJson('api/admin/car-colors', [
            'name' => 'Rojo',
            'hex' => '#FF0000',
            'sort_order' => 5,
        ])->assertCreated();

        $this->assertSame('Rojo', $response->json('data.name'));
        $this->assertSame('#FF0000', $response->json('data.hex'));
        $this->assertDatabaseHas('car_colors', ['name' => 'Rojo', 'hex' => '#FF0000']);
    }

    public function test_store_rejects_invalid_hex(): void
    {
        $this->authenticateAdmin();

        $this->postJson('api/admin/car-colors', [
            'name' => 'Rojo',
            'hex' => 'red',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['hex']);
    }

    public function test_admin_updates_color(): void
    {
        $this->authenticateAdmin();
        $color = CarColor::factory()->create(['name' => 'Azul', 'hex' => '#0000FF']);

        $this->putJson("api/admin/car-colors/{$color->id}", [
            'name' => 'Azul Marino',
            'hex' => '#000080',
            'is_active' => true,
            'sort_order' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Azul Marino')
            ->assertJsonPath('data.hex', '#000080');
    }
}
