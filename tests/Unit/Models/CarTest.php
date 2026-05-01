<?php

namespace Tests\Unit\Models;

use Database\Factories\CarFactory;
use ReflectionClass;
use STS\Models\Car;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class CarTest extends TestCase
{
    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'AB123CD',
            'description' => 'Test vehicle',
        ]);

        $this->assertTrue($car->fresh()->user->is($user));
    }

    public function test_trips_has_many(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);

        Trip::factory()->count(2)->create([
            'user_id' => $user->id,
            'car_id' => $car->id,
        ]);

        $car = $car->fresh();
        $this->assertSame(2, $car->trips()->count());
        $this->assertCount(2, $car->trips);
    }

    public function test_trips_count_append_reflects_related_trips(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);

        Trip::factory()->create(['user_id' => $user->id, 'car_id' => $car->id]);
        Trip::factory()->create(['user_id' => $user->id, 'car_id' => $car->id]);
        Trip::factory()->create(['user_id' => $user->id, 'car_id' => null]);

        $car = $car->fresh();
        $this->assertSame(2, $car->trips_count);

        $array = $car->toArray();
        $this->assertArrayHasKey('trips_count', $array);
        $this->assertSame(2, $array['trips_count']);
    }

    public function test_to_array_hides_timestamps(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);
        $array = $car->toArray();

        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);
        $this->assertArrayHasKey('patente', $array);
        $this->assertArrayHasKey('user_id', $array);
    }

    public function test_persists_patente_and_description(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'XY999ZZ',
            'description' => 'Blue hatchback',
        ]);

        $car = $car->fresh();
        $this->assertSame('XY999ZZ', $car->patente);
        $this->assertSame('Blue hatchback', $car->description);
    }

    public function test_table_name_is_cars(): void
    {
        $this->assertSame('cars', (new Car)->getTable());
    }

    public function test_new_factory_resolves_car_factory_instance(): void
    {
        $ref = new ReflectionClass(Car::class);
        $method = $ref->getMethod('newFactory');
        $method->setAccessible(true);
        $factory = $method->invoke(null);

        $this->assertInstanceOf(CarFactory::class, $factory);
    }
}
