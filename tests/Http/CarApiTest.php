<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use \STS\Contracts\Repository\Devices as DeviceRepository;

use Mockery as m;

class CarApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $carsLogic;
    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->carsLogic = $this->mock('STS\Contracts\Logic\Car');
    }

    public function tearDown()
    {
        m::close();
    } 

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testCreate()
    {
        $u1 = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->carsLogic->shouldReceive('create')->once()->andReturn($car);

        $response = $this->call('POST', 'api/cars/'); 
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->carsLogic->shouldReceive('update')->once()->andReturn($car);

        $response = $this->call('PUT', 'api/cars/' . $car->id); 
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->carsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/cars/' . $car->id); 
        $this->assertTrue($response->status() == 200);
    }

    public function testShow()
    {
        $u1 = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->carsLogic->shouldReceive('show')->once()->andReturn($car);

        $response = $this->call('GET', 'api/cars/' . $car->id); 
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($car->id == $response->data->id);
    }

    public function testIndex()
    {
        $u1 = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->carsLogic->shouldReceive('index')->once()->andReturn([$car]);

        $response = $this->call('GET', 'api/cars/'); 
        $this->assertTrue($response->status() == 200);
    }
}
