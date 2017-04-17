<?php

use Mockery as m;
use STS\Entities\Trip;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PassengerApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $logic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->logic = $this->mock('STS\Contracts\Logic\IPassengersLogic');
    }

    public function tearDown()
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testGetPassengers()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->logic->shouldReceive('index')->once()->andReturn([]);

        $response = $this->call('GET', 'api/trips/'.$trip->id.'/passengers');
        $this->assertTrue($response->status() == 200);
    }

    public function testGetPending()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->logic->shouldReceive('getPendingRequests')->once()->andReturn([]);

        $response = $this->call('GET', 'api/trips/requests');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostRequest()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u2);

        $this->logic->shouldReceive('newRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostAccept()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->logic->shouldReceive('acceptRequest')->with($trip->id, $u2->id, $u1, [])->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/accept');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostCancel()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u2);

        $this->logic->shouldReceive('cancelRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/cancel');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostReject()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u2);

        $this->logic->shouldReceive('rejectRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/reject');
        $this->assertTrue($response->status() == 200);
    }
}
