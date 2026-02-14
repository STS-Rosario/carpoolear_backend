<?php

namespace Tests\Http;

use Tests\TestCase;
use Mockery as m;
use STS\Models\Trip;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TripApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $tripsLogic;

    public function setUp(): void
    {
        parent::setUp();
        $this->tripsLogic = $this->mock(\STS\Services\Logic\TripsManager::class);
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
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('create')->once()->andReturn($trip);

        $response = $this->call('POST', 'api/trips/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('update')->once()->andReturn($trip);

        $response = $this->call('PUT', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testShow()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('show')->once()->andReturn($trip);

        $response = $this->call('GET', 'api/trips/'.$trip->id);
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($trip->id == $response->data->id);
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $response = $this->call('GET', 'api/trips/');
        $this->assertTrue($response->status() == 200);
    }

    public function testIndexWithoutLogin()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        //$this->actingAs($u1, 'api');
        $this->tripsLogic->shouldReceive('search')->once()->andReturn(Trip::paginate(10));

        $response = $this->call('GET', 'api/trips/');

        $this->assertTrue($response->status() == 200);
    }

    public function testMyTrips()
    {
        $u1 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create();
        $this->actingAs($u1, 'api');

        $this->tripsLogic->shouldReceive('getTrips')->once()->andReturn(Trip::all());

        $response = $this->call('GET', 'api/users/my-trips/');
        $this->assertTrue($response->status() == 200);
    }
}
