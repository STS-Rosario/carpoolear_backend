<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\Car;
use STS\Models\User;
use Tests\TestCase;

class AdminCarControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    private function authenticateAsAdmin(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_index_lists_cars_with_owner_basic_fields(): void
    {
        $this->authenticateAsAdmin();

        $ownerA = User::factory()->create(['name' => 'Owner A']);
        $ownerB = User::factory()->create(['name' => 'Owner B']);
        $carA = Car::factory()->for($ownerA)->create(['patente' => 'AAA111']);
        $carB = Car::factory()->for($ownerB)->create(['patente' => 'BBB222']);

        $response = $this->getJson('api/admin/cars');
        $response->assertOk();

        $cars = collect($response->json());
        $this->assertTrue($cars->contains(fn (array $item) => (int) $item['id'] === $carA->id && (int) $item['user']['id'] === $ownerA->id));
        $this->assertTrue($cars->contains(fn (array $item) => (int) $item['id'] === $carB->id && (int) $item['user']['id'] === $ownerB->id));
    }

    public function test_show_returns_single_car_with_owner(): void
    {
        $this->authenticateAsAdmin();

        $owner = User::factory()->create(['name' => 'Single Owner']);
        $car = Car::factory()->for($owner)->create(['patente' => 'CCC333', 'description' => 'Sedan']);

        $this->getJson("api/admin/cars/{$car->id}")
            ->assertOk()
            ->assertJsonPath('id', $car->id)
            ->assertJsonPath('patente', 'CCC333')
            ->assertJsonPath('description', 'Sedan')
            ->assertJsonPath('user.id', $owner->id)
            ->assertJsonPath('user.name', 'Single Owner');
    }

    public function test_update_persists_valid_payload_and_returns_loaded_owner(): void
    {
        $this->authenticateAsAdmin();

        $owner = User::factory()->create();
        $car = Car::factory()->for($owner)->create(['patente' => 'DDD444', 'description' => 'Old']);

        $this->putJson("api/admin/cars/{$car->id}", [
            'patente' => 'EEE555',
            'description' => 'New description',
        ])
            ->assertOk()
            ->assertJsonPath('id', $car->id)
            ->assertJsonPath('patente', 'EEE555')
            ->assertJsonPath('description', 'New description')
            ->assertJsonPath('user.id', $owner->id);

        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'patente' => 'EEE555',
            'description' => 'New description',
        ]);
    }

    public function test_update_rejects_duplicate_patente_used_by_another_car(): void
    {
        $this->authenticateAsAdmin();

        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $target = Car::factory()->for($ownerA)->create(['patente' => 'FFF666', 'description' => 'Target']);
        Car::factory()->for($ownerB)->create(['patente' => 'GGG777', 'description' => 'Other']);

        $this->putJson("api/admin/cars/{$target->id}", [
            'patente' => 'GGG777',
            'description' => 'Still target',
        ])->assertStatus(422);

        $this->assertSame('FFF666', $target->fresh()->patente);
    }

    public function test_destroy_removes_car_record(): void
    {
        $this->authenticateAsAdmin();

        $car = Car::factory()->for(User::factory())->create(['patente' => 'HHH888']);

        $this->deleteJson("api/admin/cars/{$car->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cars', ['id' => $car->id]);
    }

    public function test_user_cars_and_store_for_user_cover_admin_user_scoped_flow(): void
    {
        $this->authenticateAsAdmin();

        $owner = User::factory()->create();

        $this->postJson("api/admin/users/{$owner->id}/cars", [
            'patente' => 'III999',
            'description' => 'Created by admin',
        ])
            ->assertCreated()
            ->assertJsonPath('patente', 'III999')
            ->assertJsonPath('description', 'Created by admin')
            ->assertJsonPath('user.id', $owner->id);

        $this->getJson("api/admin/users/{$owner->id}/cars")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.patente', 'III999');
    }
}
