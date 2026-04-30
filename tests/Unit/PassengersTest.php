<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Events\Passenger\Cancel as CancelEvent;
use STS\Events\Passenger\Reject as RejectEvent;
use STS\Events\Passenger\Request as RequestEvent;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\PassengersRepository;
use STS\Services\Logic\PassengersManager;
use Tests\TestCase;

class PassengersTest extends TestCase
{
    private PassengersManager $passengerManager;

    private PassengersRepository $passengerRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passengerManager = $this->app->make(PassengersManager::class);
        $this->passengerRepository = $this->app->make(PassengersRepository::class);

        Config::set('carpoolear.module_unaswered_message_limit', false);
        Config::set('carpoolear.module_user_request_limited', false);
        Config::set('carpoolear.module_trip_seats_payment', false);
        Config::set('carpoolear.module_send_full_trip_message', false);
        Carbon::setTestNow('2028-01-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createFutureTripWithDriver(): array
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'total_seats' => 4,
        ]);

        return [$driver, $trip];
    }

    public function test_new_request_creates_pending_request_and_dispatches_event(): void
    {
        Event::fake([RequestEvent::class]);
        [$driver, $trip] = $this->createFutureTripWithDriver();
        $passenger = User::factory()->create();

        $result = $this->passengerManager->newRequest($trip->id, $passenger);

        $this->assertNotNull($result);
        $this->assertSame(Passenger::STATE_PENDING, (int) $result->fresh()->request_state);
        $this->assertSame($trip->id, (int) $result->trip_id);
        $this->assertSame($passenger->id, (int) $result->user_id);
        Event::assertDispatched(RequestEvent::class);
    }

    public function test_accept_request_moves_passenger_to_accepted_and_dispatches_event(): void
    {
        Event::fake([AcceptEvent::class]);
        [$driver, $trip] = $this->createFutureTripWithDriver();
        $passenger = User::factory()->create();

        $this->passengerRepository->newRequest($trip->id, $passenger);
        $result = $this->passengerManager->acceptRequest($trip->id, $passenger->id, $driver);

        $this->assertNotNull($result);
        $this->assertSame(Passenger::STATE_ACCEPTED, (int) $result->fresh()->request_state);
        $this->assertTrue($this->passengerManager->isUserRequestAccepted($trip->id, $passenger->id));
        Event::assertDispatched(AcceptEvent::class);
    }

    public function test_reject_request_moves_passenger_to_rejected_and_dispatches_event(): void
    {
        Event::fake([RejectEvent::class]);
        [$driver, $trip] = $this->createFutureTripWithDriver();
        $passenger = User::factory()->create();

        $this->passengerRepository->newRequest($trip->id, $passenger);
        $result = $this->passengerManager->rejectRequest($trip->id, $passenger->id, $driver);

        $this->assertNotNull($result);
        $this->assertSame(Passenger::STATE_REJECTED, (int) $result->fresh()->request_state);
        Event::assertDispatched(RejectEvent::class);
    }

    public function test_cancel_request_from_passenger_marks_canceled_state_and_dispatches_event(): void
    {
        Event::fake([CancelEvent::class]);
        [$driver, $trip] = $this->createFutureTripWithDriver();
        $passenger = User::factory()->create();
        $this->passengerRepository->newRequest($trip->id, $passenger);

        $result = $this->passengerManager->cancelRequest($trip->id, $passenger->id, $passenger);
        $this->assertNotNull($result);
        $this->assertSame(Passenger::STATE_CANCELED, (int) $result->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_REQUEST, (int) $result->fresh()->canceled_state);
        Event::assertDispatched(CancelEvent::class);
    }

    public function test_get_passengers_returns_only_accepted_rows_for_owner(): void
    {
        [$driver, $trip] = $this->createFutureTripWithDriver();
        $passengerA = User::factory()->create();
        $passengerB = User::factory()->create();

        Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);
        Passenger::factory()->create([
            'user_id' => User::factory()->create()->id,
            'trip_id' => $trip->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $result = $this->passengerManager->getPassengers($trip->id, $driver, []);
        $this->assertNotNull($result);
        $this->assertCount(2, $result);
    }

    public function test_get_pending_requests_returns_pending_for_driver_scope(): void
    {
        [$driver, $trip] = $this->createFutureTripWithDriver();
        $passengerA = User::factory()->create();
        $passengerB = User::factory()->create();

        Passenger::factory()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $result = $this->passengerManager->getPendingRequests($trip->id, $driver, []);
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertSame($passengerA->id, $result->first()->user_id);

        $result = $this->passengerManager->getPendingRequests(null, $driver, []);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }
}
