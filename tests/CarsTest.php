<?php

namespace Tests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CarsTest extends TestCase
{
    use DatabaseTransactions;

    protected $carManager;

    public function setUp(): void
    {
        parent::setUp();
        start_log_query();
        $this->carManager = \App::make(\STS\Services\Logic\CarsManager::class);
    }

    public function testCreateCar()
    {
        $user = \STS\Models\User::factory()->create();
        $data = [
            'patente'       => 'ASD 123',
            'description'   => 'Sandero',
        ];

        $car = $this->carManager->create($user, $data);
        $this->assertTrue($car != null);
    }

    public function testUpdateCar()
    {
        $user = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $user->id]);
        $data = [
            'patente'       => 'SOF 034',
            'description'   => 'Sandero',
        ];

        $updated_car = $this->carManager->update($user, $car->id, $data);
        $this->assertTrue($car->patente != $updated_car->patente);
    }

    public function testShowCar()
    {
        $user = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $user->id]);

        $showed_car = $this->carManager->show($user, $car->id);
        $this->assertTrue($car->patente == $showed_car->patente);
    }

    public function testDeleteCar()
    {
        $user = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $user->id]);

        $result = $this->carManager->delete($user, $car->id);
        $this->assertTrue($result);
    }

    public function testIndexCar()
    {
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();
        \STS\Models\Car::factory()->create(['user_id' => $user1->id]);
        \STS\Models\Car::factory()->create(['user_id' => $user2->id]);

        $result = $this->carManager->index($user1);
        $this->assertTrue($result->count() >= 1);
    }
}
