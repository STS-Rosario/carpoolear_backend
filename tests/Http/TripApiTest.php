<?php

use Mockery as m;
use STS\Entities\Trip;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TripApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $tripsLogic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->tripsLogic = $this->mock('STS\Contracts\Logic\Trip');
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
        $this->actingAsApiUser($u1);

        $this->tripsLogic->shouldReceive('create')->once()->andReturn($u1);

        $response = $this->call('POST', 'api/trips/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        $this->actingAsApiUser($u1);

        $this->tripsLogic->shouldReceive('update')->once()->andReturn($trip);

        $response = $this->call('PUT', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        $this->actingAsApiUser($u1);

        $this->tripsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testShow()
    {
        $u1 = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        $this->actingAsApiUser($u1);

        $this->tripsLogic->shouldReceive('show')->once()->andReturn($trip);

        $response = $this->call('GET', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($trip->id == $response->data->id);
    }

    public function testIndex()
    {
        $u1 = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        $this->actingAsApiUser($u1);

        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $response = $this->call('GET', 'api/trips/');
        $this->assertTrue($response->status() == 200);
    }

    public function testIndexWithoutLogin()
    {
        $u1 = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        //$this->actingAsApiUser($u1);
        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $response = $this->call('GET', 'api/trips/');

        $this->assertTrue($response->status() == 200);
    }

    public function testMyTrips()
    {
        $u1 = factory(STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create();
        $this->actingAsApiUser($u1);

        $this->tripsLogic->shouldReceive('myTrips')->once()->andReturn([]);

        $response = $this->call('GET', 'api/users/my-trips/');
        $this->assertTrue($response->status() == 200);
    }
}
