<?php

namespace Tests\Feature\Http;

use Tests\TestCase;
use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CarApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $carsLogic;

    public function setUp(): void
    {
        parent::setUp();
        $this->carsLogic = $this->mock(\STS\Services\Logic\CarsManager::class);
    }

    public function tearDown(): void
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testCreate()
    {
        $u1 = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->carsLogic->shouldReceive('create')->once()->andReturn($car);

        $response = $this->call('POST', 'api/cars/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->carsLogic->shouldReceive('update')->once()->andReturn($car);

        $response = $this->call('PUT', 'api/cars/'.$car->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->carsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/cars/'.$car->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testShow()
    {
        $u1 = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->carsLogic->shouldReceive('show')->once()->andReturn($car);

        $response = $this->call('GET', 'api/cars/'.$car->id);
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($car->id == $response->data->id);
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $car = \STS\Models\Car::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->carsLogic->shouldReceive('index')->once()->andReturn([$car]);

        $response = $this->call('GET', 'api/cars/');
        $this->assertTrue($response->status() == 200);
    }
}
