<?php

namespace Tests\Feature\Http;

use Tests\TestCase;
use Mockery as m;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PassengerApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $logic;

    public function setUp(): void
    {
        parent::setUp();
        $this->logic = $this->mock(\STS\Services\Logic\PassengersManager::class);
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testGetPassengers()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('index')->once()->andReturn(Passenger::all());

        $response = $this->call('GET', 'api/trips/'.$trip->id.'/passengers');
        $this->assertTrue($response->status() == 200);
    }

    public function testGetPending()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('getPendingRequests')->once()->andReturn(Passenger::all());

        $response = $this->call('GET', 'api/trips/requests');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostRequest()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u2, 'api');

        $this->logic->shouldReceive('newRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostAccept()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('acceptRequest')->with($trip->id, $u2->id, $u1, [])->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/accept');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostCancel()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u2, 'api');

        $this->logic->shouldReceive('cancelRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/cancel');
        $this->assertTrue($response->status() == 200);
    }

    public function testPostReject()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u2, 'api');

        $this->logic->shouldReceive('rejectRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/reject');
        $this->assertTrue($response->status() == 200);
    }
}
