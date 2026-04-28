<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\Car;
use STS\Models\User;
use STS\Services\Logic\CarsManager;
use Tests\TestCase;

class CarsTest extends TestCase
{
    use DatabaseTransactions;

    private CarsManager $carManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carManager = $this->app->make(CarsManager::class);
    }

    public function test_create_car_persists_for_user(): void
    {
        $user = User::factory()->create();
        $data = [
            'patente' => 'ASD123',
            'description' => 'Sandero',
        ];

        $car = $this->carManager->create($user, $data);
        $this->assertNotNull($car);
        $this->assertSame($user->id, (int) $car->user_id);
        $this->assertSame('ASD123', $car->patente);
        $this->assertDatabaseHas('cars', [
            'id' => $car->id,
            'user_id' => $user->id,
            'patente' => 'ASD123',
        ]);
    }

    public function test_update_car_changes_patente_and_description(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id, 'patente' => 'SOF033']);
        $data = [
            'patente' => 'SOF034',
            'description' => 'Sandero RS',
        ];

        $updatedCar = $this->carManager->update($user, $car->id, $data);
        $this->assertNotNull($updatedCar);
        $this->assertSame('SOF034', $updatedCar->fresh()->patente);
        $this->assertSame('Sandero RS', $updatedCar->fresh()->description);
    }

    public function test_show_car_allows_owner_and_denies_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);

        $shownCar = $this->carManager->show($user, $car->id);
        $this->assertNotNull($shownCar);
        $this->assertTrue($shownCar->is($car));

        $otherManager = $this->app->make(CarsManager::class);
        $this->assertNull($otherManager->show($other, $car->id));
        $this->assertSame('car_not_found', $otherManager->getErrors()['error']);
    }

    public function test_delete_car_removes_row_for_owner(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create(['user_id' => $user->id]);

        $result = $this->carManager->delete($user, $car->id);
        $this->assertTrue($result);
        $this->assertNull(Car::query()->find($car->id));
    }

    public function test_index_car_returns_only_users_cars(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $car1 = Car::factory()->create(['user_id' => $user1->id]);
        Car::factory()->create(['user_id' => $user2->id]);

        $result = $this->carManager->index($user1);
        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->is($car1));
    }
}
