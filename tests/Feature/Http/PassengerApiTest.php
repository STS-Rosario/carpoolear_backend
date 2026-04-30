<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery as m;
use STS\Models\Passenger;
use Tests\TestCase;

class PassengerApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $logic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logic = $this->mock(\STS\Services\Logic\PassengersManager::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function test_get_passengers()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('getPassengers')->once()->andReturn(Passenger::all());

        $response = $this->call('GET', 'api/trips/'.$trip->id.'/passengers');
        $this->assertTrue($response->status() == 200);
    }

    public function test_get_pending()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('getPendingRequests')->once()->andReturn(Passenger::all());

        $response = $this->call('GET', 'api/trips/requests');
        $this->assertTrue($response->status() == 200);
    }

    public function test_post_request()
    {
        $u1 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $u2 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u2, 'api');

        $this->logic->shouldReceive('newRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests');
        $this->assertTrue($response->status() == 200);
    }

    public function test_post_accept()
    {
        $u1 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $u2 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('acceptRequest')->with($trip->id, $u2->id, $u1, [])->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/accept');
        $this->assertTrue($response->status() == 200);
    }

    public function test_post_cancel()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u2, 'api');

        $this->logic->shouldReceive('cancelRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/cancel');
        $this->assertTrue($response->status() == 200);
    }

    public function test_post_reject()
    {
        $u1 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $u2 = \STS\Models\User::factory()->create(['identity_validated' => true]);
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('rejectRequest')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/'.$trip->id.'/requests/'.$u2->id.'/reject');
        $this->assertTrue($response->status() == 200);
    }
}
