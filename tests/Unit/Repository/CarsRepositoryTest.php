<?php

namespace Tests\Unit\Repository;

use STS\Models\Car;
use STS\Models\User;
use STS\Repository\CarsRepository;
use Tests\TestCase;

class CarsRepositoryTest extends TestCase
{
    private function repo(): CarsRepository
    {
        return new CarsRepository;
    }

    public function test_create_persists_car(): void
    {
        $user = User::factory()->create();
        $car = new Car([
            'user_id' => $user->id,
            'patente' => 'AA-'.substr(uniqid('', true), 0, 6),
            'description' => 'Test vehicle',
        ]);

        $this->assertTrue($this->repo()->create($car));

        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'user_id' => $user->id,
            'patente' => $car->patente,
        ]);
    }

    public function test_update_persists_changes(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'BB-'.substr(uniqid('', true), 0, 6),
            'description' => 'Before',
        ]);

        $car->description = 'After update';
        $this->assertTrue($this->repo()->update($car));

        $this->assertSame('After update', $car->fresh()->description);
    }

    public function test_show_returns_car_or_null(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'CC-'.substr(uniqid('', true), 0, 6),
        ]);

        $found = $this->repo()->show($car->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($car));

        $this->assertNull($this->repo()->show(999_999_999));
    }

    public function test_delete_removes_car(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'DD-'.substr(uniqid('', true), 0, 6),
        ]);
        $id = $car->id;

        $this->assertTrue((bool) $this->repo()->delete($car));

        $this->assertNull(Car::query()->find($id));
    }

    public function test_index_returns_user_cars_relation(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'EE-'.substr(uniqid('', true), 0, 6),
        ]);

        $cars = $this->repo()->index($user->fresh());

        $this->assertCount(1, $cars);
        $this->assertTrue($cars->first()->is($car));
    }

    public function test_index_returns_empty_when_user_has_no_cars(): void
    {
        // Mutation intent: preserve `$user->cars` empty relation (~30–33).
        $user = User::factory()->create();

        $cars = $this->repo()->index($user->fresh());

        $this->assertCount(0, $cars);
    }

    public function test_get_user_car_returns_first_row_for_user(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $c1 = Car::factory()->create([
            'user_id' => $u1->id,
            'patente' => 'FF-'.substr(uniqid('', true), 0, 6),
        ]);
        $c2 = Car::factory()->create([
            'user_id' => $u2->id,
            'patente' => 'GG-'.substr(uniqid('', true), 0, 6),
        ]);

        $this->assertTrue($this->repo()->getUserCar($u1->id)->is($c1));
        $this->assertTrue($this->repo()->getUserCar($u2->id)->is($c2));
    }

    public function test_get_user_car_returns_null_when_user_has_no_car(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->repo()->getUserCar($user->id));
    }
}
