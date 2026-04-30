<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery as m;
use STS\Http\Controllers\Api\v1\PassengerController;
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

    public function test_constructor_registers_logged_middleware(): void
    {
        $controller = new PassengerController(
            m::mock(Request::class),
            m::mock(\STS\Services\Logic\PassengersManager::class)
        );

        $logged = collect($controller->getMiddleware())->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);
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

    public function test_get_trip_pending_requests_returns_collection_payload(): void
    {
        $owner = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner, 'api');

        $this->logic->shouldReceive('getPendingRequests')->once()->andReturn(collect([]));

        $this->getJson('api/trips/'.$trip->id.'/requests')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_get_all_requests_returns_collection_payload(): void
    {
        $user = \STS\Models\User::factory()->create();
        $this->actingAs($user, 'api');

        $this->logic->shouldReceive('getPendingRequests')->once()->andReturn(collect([]));

        $this->getJson('api/trips/requests')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_transactions_endpoint_returns_logic_response(): void
    {
        $user = \STS\Models\User::factory()->create();
        $this->actingAs($user, 'api');

        $expected = ['balance' => 1234, 'items' => []];
        $this->logic->shouldReceive('transactions')->once()->with($user)->andReturn($expected);

        $this->getJson('api/trips/transactions')
            ->assertOk()
            ->assertExactJson($expected);
    }

    public function test_payment_pending_request_method_returns_collection_payload(): void
    {
        $user = \STS\Models\User::factory()->create();
        $this->actingAs($user, 'api');

        $this->logic->shouldReceive('getPendingPaymentRequests')->once()->andReturn(collect([]));

        $controller = app()->make(PassengerController::class);
        $response = $controller->paymentPendingRequest(Request::create('/api/trips/payment-pending', 'GET'));

        $payload = json_decode($response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
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

    public function test_pay_request_returns_data_payload_on_success(): void
    {
        $driver = \STS\Models\User::factory()->create();
        $passenger = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);
        $this->actingAs($driver, 'api');

        $payload = ['status' => 'paid'];
        $this->logic->shouldReceive('payRequest')
            ->once()
            ->with($trip->id, $passenger->id, $driver, [])
            ->andReturn($payload);

        $this->postJson('api/trips/'.$trip->id.'/requests/'.$passenger->id.'/pay')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_pay_request_returns_unprocessable_with_expected_message_when_logic_fails(): void
    {
        $driver = \STS\Models\User::factory()->create();
        $passenger = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);
        $this->actingAs($driver, 'api');

        $this->logic->shouldReceive('payRequest')->once()->andReturn(false);
        $this->logic->shouldReceive('getErrors')->once()->andReturn(['error' => ['not_allowed']]);

        $this->postJson('api/trips/'.$trip->id.'/requests/'.$passenger->id.'/pay')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not accept request.')
            ->assertJsonPath('errors.error.0', 'not_allowed');
    }
}
