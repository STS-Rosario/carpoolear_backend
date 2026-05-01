<?php

namespace Tests\Feature\Http;

use STS\Models\Car;
use STS\Models\User;
use Tests\TestCase;

class CarApiTest extends TestCase
{
    public function test_car_routes_require_authentication(): void
    {
        $this->getJson('api/cars')->assertUnauthorized()->assertJson(['message' => 'Unauthorized.']);
        $this->postJson('api/cars', [])->assertUnauthorized()->assertJson(['message' => 'Unauthorized.']);
        $this->putJson('api/cars/1', [])->assertUnauthorized()->assertJson(['message' => 'Unauthorized.']);
        $this->deleteJson('api/cars/1')->assertUnauthorized()->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_index_returns_empty_json_array_when_user_has_no_cars(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $this->getJson('api/cars')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_index_returns_json_array_of_user_cars(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'IDX1',
            'description' => 'Listed car',
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('api/cars');
        $response->assertOk();
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);
        $this->assertSame('IDX1', $payload[0]['patente']);
    }

    public function test_create_show_update_delete_lifecycle(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $create = $this->postJson('api/cars', [
            'patente' => 'CR1',
            'description' => 'Compact',
        ]);
        $create->assertOk();
        $create->assertJsonStructure(['data' => ['id', 'patente', 'description', 'user_id', 'trips_count']]);
        $create->assertJsonPath('data.patente', 'CR1');
        $create->assertJsonPath('data.description', 'Compact');

        $carId = (int) $create->json('data.id');

        $this->getJson('api/cars/'.$carId)
            ->assertOk()
            ->assertJsonPath('data.id', $carId)
            ->assertJsonPath('data.patente', 'CR1');

        $this->putJson('api/cars/'.$carId, [
            'patente' => 'CR1U',
            'description' => 'Updated compact',
        ])
            ->assertOk()
            ->assertJsonPath('data.patente', 'CR1U')
            ->assertJsonPath('data.description', 'Updated compact');

        $this->deleteJson('api/cars/'.$carId)
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertNull(Car::find($carId));
    }

    public function test_show_returns_unprocessable_when_car_missing_or_not_owned(): void
    {
        $owner = User::factory()->create(['active' => true, 'banned' => false]);
        $other = User::factory()->create(['active' => true, 'banned' => false]);
        $car = Car::factory()->create(['user_id' => $owner->id, 'patente' => 'OWN1']);

        $this->actingAs($other, 'api');
        $this->getJson('api/cars/'.$car->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Could not found car.');

        $this->actingAs($owner, 'api');
        $this->getJson('api/cars/999999999')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Could not found car.');
    }

    public function test_create_returns_unprocessable_when_validation_fails(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $this->postJson('api/cars', [
            'patente' => '',
            'description' => '',
        ])->assertStatus(422);
    }

    public function test_create_returns_unprocessable_when_user_already_has_a_car(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        Car::factory()->create(['user_id' => $user->id, 'patente' => 'HAS1']);

        $this->actingAs($user, 'api');

        $this->postJson('api/cars', [
            'patente' => 'HAS2',
            'description' => 'Second car attempt',
        ])->assertStatus(422);
    }
}
