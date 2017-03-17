<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use \STS\Contracts\Logic\User as UserLogic;
use \STS\Contracts\Logic\Trip as TripsLogic;
use Carbon\Carbon;
use Mockery as m;
use STS\Entities\TripPoint;
use STS\Entities\Trip;

class TripsTest extends TestCase
{
    use DatabaseTransactions;
 
    protected $carManager;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
        $this->carManager = \App::make('\STS\Contracts\Logic\Car');
    }

    public function testCreateCar()
    { 
        $user = factory(STS\User::class)->create();
        $data = [
            'patente'       => 'ASD 123',
            'description'   => 'Sandero',
        ];

        $car = $this->carManager->create($user, $data);
        $this->assertTrue($car != null);
    }

    public function testUpdateCar()
    { 
        $user = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $user->id]);
        $data = [
            'patente'       => 'SOF 034',
            'description'   => 'Sandero',
        ];

        $updated_car = $this->carManager->update($user, $car->id, $data); 
        $this->assertTrue($car->patente != $updated_car->patente);
    }

    public function testShowCar()
    { 
        $user = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $user->id]);

        $showed_car = $this->carManager->show($user, $car->id); 
        $this->assertTrue($car->patente == $showed_car->patente);
    }

    public function testDeleteCar()
    { 
        $user = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $user->id]);

        $result = $this->carManager->delete($user, $car->id); 
        $this->assertTrue($result);
    }

    public function testIndexCar()
    { 
        $user = factory(STS\User::class)->create();
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $user->id]);
        $car = factory(STS\Entities\Car::class)->create(['user_id' => $user->id]);

        $result = $this->carManager->index($user); 
        $this->assertTrue($result->count() == 2);
    }
}
