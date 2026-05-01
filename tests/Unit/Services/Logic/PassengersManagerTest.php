<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\MessageBag;
use Mockery;
use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Events\Passenger\AutoRequest as AutoRequestEvent;
use STS\Events\Passenger\Cancel as CancelEvent;
use STS\Events\Passenger\Reject as RejectEvent;
use STS\Events\Passenger\Request as RequestEvent;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Logic\PassengersManager;
use Tests\TestCase;

class PassengersManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('carpoolear.module_unaswered_message_limit', false);
        Config::set('carpoolear.module_user_request_limited', false);
        Config::set('carpoolear.module_trip_seats_payment', false);
        Config::set('carpoolear.module_send_full_trip_message', false);
    }

    private function manager(): PassengersManager
    {
        return $this->app->make(PassengersManager::class);
    }

    public function test_get_passengers_allows_owner_and_denies_stranger(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $pax = User::factory()->create();
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $pax->id]);

        $list = $this->manager()->getPassengers($trip->id, $driver, []);
        $this->assertNotNull($list);

        $manager = $this->manager();
        $this->assertNull($manager->getPassengers($trip->id, User::factory()->create(), []));
        $this->assertSame('access_denied', $manager->getErrors()['error']);
    }

    public function test_get_passengers_denies_accepted_passenger_who_is_not_owner(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $accepted = User::factory()->create();
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $accepted->id]);

        $manager = $this->manager();
        $this->assertNull($manager->getPassengers($trip->id, $accepted, []));
        $this->assertSame('access_denied', $manager->getErrors()['error']);
    }

    public function test_get_pending_requests_with_trip_id_requires_owner(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->getPendingRequests($trip->id, User::factory()->create(), []));
        $this->assertSame('access_denied', $manager->getErrors()['error']);

        $this->assertNotNull($this->manager()->getPendingRequests($trip->id, $driver, []));
    }

    public function test_get_pending_payment_requests_with_trip_id_requires_owner(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->getPendingPaymentRequests($trip->id, User::factory()->create(), []));
        $this->assertSame('access_denied', $manager->getErrors()['error']);

        $this->assertNotNull($this->manager()->getPendingPaymentRequests($trip->id, $driver, []));
    }

    public function test_new_request_creates_pending_and_dispatches_request_event(): void
    {
        Event::fake([RequestEvent::class]);
        $driver = User::factory()->create(['autoaccept_requests' => false]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create();

        $row = $this->manager()->newRequest($trip->id, $requester, []);

        $this->assertInstanceOf(Passenger::class, $row);
        $this->assertSame(Passenger::STATE_PENDING, (int) $row->fresh()->request_state);
        Event::assertDispatched(RequestEvent::class);
    }

    public function test_new_request_returns_null_when_trip_expired(): void
    {
        Event::fake([RequestEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->subDay(),
        ]);
        $requester = User::factory()->create();

        $manager = $this->manager();
        $this->assertNull($manager->newRequest($trip->id, $requester, []));
        $this->assertSame('access_denied', $manager->getErrors()['error']);
        Event::assertNotDispatched(RequestEvent::class);
    }

    public function test_new_request_returns_null_when_duplicate_active_request(): void
    {
        Event::fake([RequestEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->assertNull($this->manager()->newRequest($trip->id, $requester, []));
        Event::assertNotDispatched(RequestEvent::class);
    }

    public function test_new_request_sets_validation_errors_and_stops_when_trip_id_is_invalid(): void
    {
        Event::fake([RequestEvent::class]);
        $requester = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->newRequest(null, $requester, []));

        $errors = $manager->getErrors();
        $this->assertInstanceOf(MessageBag::class, $errors);
        $this->assertTrue($errors->has('trip_id'));
        $this->assertFalse($errors->has('error'));
        Event::assertNotDispatched(RequestEvent::class);
    }

    public function test_internal_request_validation_requires_user_id(): void
    {
        $manager = $this->manager();
        $method = (new \ReflectionClass($manager))->getMethod('validateInput');
        $method->setAccessible(true);
        $validator = $method->invoke($manager, ['trip_id' => 1]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('user_id'));
    }

    public function test_new_request_blocks_when_user_request_limited_module_detects_similar_trip(): void
    {
        Event::fake([RequestEvent::class]);
        Config::set('carpoolear.module_user_request_limited', (object) [
            'enabled' => true,
            'hours_range' => 48,
        ]);
        $driverA = User::factory()->create(['autoaccept_requests' => false]);
        $driverB = User::factory()->create();
        $tripDate = Carbon::parse('2028-07-10 10:00:00');
        $tripA = Trip::factory()->create([
            'user_id' => $driverA->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => $tripDate,
        ]);
        $tripB = Trip::factory()->create([
            'user_id' => $driverB->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => $tripDate->copy()->addHours(12),
        ]);
        $requester = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $tripB->id,
            'user_id' => $requester->id,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->newRequest($tripA->id, $requester, []));
        $this->assertSame('user_has_another_similar_trip', $manager->getErrors()['error']);
        $this->assertSame(0, Passenger::query()->where('trip_id', $tripA->id)->where('user_id', $requester->id)->count());
        Event::assertNotDispatched(RequestEvent::class);
    }

    public function test_new_request_sets_limit_error_when_unanswered_limit_module_blocks_user(): void
    {
        Event::fake([RequestEvent::class, AcceptEvent::class, AutoRequestEvent::class]);
        Config::set('carpoolear.module_unaswered_message_limit', true);
        $driver = User::factory()->create(['autoaccept_requests' => false]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $requester = User::factory()->create();

        $usersManagerMock = \Mockery::mock(\STS\Services\Logic\UsersManager::class);
        $usersManagerMock->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(\Mockery::type(Trip::class))
            ->andReturn(false);
        $this->app->instance(\STS\Services\Logic\UsersManager::class, $usersManagerMock);

        $manager = $this->manager();
        $this->assertNull($manager->newRequest($trip->id, $requester, []));
        $this->assertSame('user_has_reach_request_limit', $manager->getErrors()['error']);
        $this->assertSame(0, Passenger::query()->where('trip_id', $trip->id)->where('user_id', $requester->id)->count());
        Event::assertNotDispatched(RequestEvent::class);
        Event::assertNotDispatched(AcceptEvent::class);
        Event::assertNotDispatched(AutoRequestEvent::class);
    }

    public function test_new_request_autoaccept_dispatches_auto_and_accept_events(): void
    {
        Event::fake([RequestEvent::class, AcceptEvent::class, AutoRequestEvent::class]);
        $driver = User::factory()->create(['autoaccept_requests' => true]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
            'total_seats' => 4,
        ]);
        $requester = User::factory()->create();

        $row = $this->manager()->newRequest($trip->id, $requester, []);

        $this->assertNotNull($row);
        $this->assertSame(Passenger::STATE_ACCEPTED, (int) $row->fresh()->request_state);
        Event::assertNotDispatched(RequestEvent::class);
        Event::assertDispatched(AutoRequestEvent::class);
        Event::assertDispatched(AcceptEvent::class);
    }

    public function test_cancel_request_sets_validation_errors_when_input_invalid(): void
    {
        $manager = $this->manager();
        $manager->cancelRequest(null, 1, User::factory()->create(), []);

        $errors = $manager->getErrors();
        $this->assertInstanceOf(MessageBag::class, $errors);
        $this->assertTrue($errors->has('trip_id'));
    }

    public function test_cancel_request_passenger_pending_self_cancel(): void
    {
        Event::fake([CancelEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->manager()->cancelRequest($trip->id, $passenger->id, $passenger, []);

        Event::assertDispatched(CancelEvent::class);
        $this->assertSame(Passenger::STATE_CANCELED, (int) Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->value('request_state'));
    }

    public function test_cancel_request_passenger_while_waiting_payment_sets_while_paying_state(): void
    {
        Event::fake([CancelEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->manager()->cancelRequest($trip->id, $passenger->id, $passenger, []);

        Event::assertDispatched(CancelEvent::class);
        $row = Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->first();
        $this->assertSame(Passenger::STATE_CANCELED, (int) $row->request_state);
        $this->assertSame(Passenger::CANCELED_PASSENGER_WHILE_PAYING, (int) $row->canceled_state);
    }

    public function test_cancel_request_driver_cancels_accepted_passenger(): void
    {
        Event::fake([CancelEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $passenger->id]);

        $this->manager()->cancelRequest($trip->id, $passenger->id, $driver, []);

        Event::assertDispatched(CancelEvent::class);
        $this->assertSame(Passenger::CANCELED_DRIVER, (int) Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->value('canceled_state'));
    }

    public function test_cancel_request_sets_not_a_passenger_when_no_applicable_state(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $stranger = User::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->cancelRequest($trip->id, $passenger->id, $stranger, []));
        $this->assertSame('not_a_passenger', $manager->getErrors()['error']);
    }

    public function test_accept_request_accepts_pending_and_dispatches_accept_event(): void
    {
        Event::fake([AcceptEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 3,
        ]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->manager()->acceptRequest($trip->id, $passenger->id, $driver, []);

        $this->assertSame(Passenger::STATE_ACCEPTED, (int) Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->value('request_state'));
        Event::assertDispatched(AcceptEvent::class);
    }

    public function test_accept_request_sets_not_seat_available_when_full(): void
    {
        Event::fake([AcceptEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 1,
        ]);
        $occupant = User::factory()->create();
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $occupant->id]);
        $pending = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $pending->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->acceptRequest($trip->id, $pending->id, $driver, []));
        $this->assertSame('not_seat_available', $manager->getErrors()['error']);
        Event::assertNotDispatched(AcceptEvent::class);
    }

    public function test_accept_request_sets_not_valid_request_for_non_owner(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->acceptRequest($trip->id, $passenger->id, User::factory()->create(), []));
        $this->assertSame('not_valid_request', $manager->getErrors()['error']);
    }

    public function test_accept_request_moves_to_waiting_payment_when_seats_payment_module_enabled(): void
    {
        Event::fake([AcceptEvent::class]);
        Config::set('carpoolear.module_trip_seats_payment', true);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 3,
        ]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $conversationManagerMock = Mockery::mock(\STS\Services\Logic\ConversationsManager::class);
        $conversationManagerMock->shouldReceive('sendFullTripMessage')->never();
        $this->app->instance(\STS\Services\Logic\ConversationsManager::class, $conversationManagerMock);

        $this->manager()->acceptRequest($trip->id, $passenger->id, $driver, []);

        $this->assertSame(Passenger::STATE_WAITING_PAYMENT, (int) Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->value('request_state'));
        Event::assertDispatched(AcceptEvent::class);
    }

    public function test_new_request_autoaccept_with_payment_module_dispatches_auto_and_accept_events(): void
    {
        Event::fake([RequestEvent::class, AcceptEvent::class, AutoRequestEvent::class]);
        Config::set('carpoolear.module_trip_seats_payment', true);
        $driver = User::factory()->create(['autoaccept_requests' => true]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::now()->addDay(),
            'total_seats' => 4,
        ]);
        $requester = User::factory()->create();

        $conversationManagerMock = Mockery::mock(\STS\Services\Logic\ConversationsManager::class);
        $conversationManagerMock->shouldReceive('sendFullTripMessage')->never();
        $this->app->instance(\STS\Services\Logic\ConversationsManager::class, $conversationManagerMock);

        $row = $this->manager()->newRequest($trip->id, $requester, []);

        $this->assertNotNull($row);
        $this->assertSame(Passenger::STATE_WAITING_PAYMENT, (int) $row->fresh()->request_state);
        Event::assertNotDispatched(RequestEvent::class);
        Event::assertDispatched(AutoRequestEvent::class);
        Event::assertDispatched(AcceptEvent::class);
    }

    public function test_reject_request_rejects_pending_and_dispatches_reject_event(): void
    {
        Event::fake([RejectEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->manager()->rejectRequest($trip->id, $passenger->id, $driver, []);

        $this->assertSame(Passenger::STATE_REJECTED, (int) Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->value('request_state'));
        Event::assertDispatched(RejectEvent::class);
    }

    public function test_send_full_trip_message_calls_conversation_manager_when_module_enabled_and_trip_is_full(): void
    {
        Config::set('carpoolear.module_send_full_trip_message', true);
        $driver = User::factory()->create(['send_full_trip_message' => 1]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 1,
        ]);
        $passenger = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        $trip->load(['user', 'passengerAccepted']);

        $conversationManagerMock = \Mockery::mock(\STS\Services\Logic\ConversationsManager::class);
        $conversationManagerMock->shouldReceive('sendFullTripMessage')
            ->once()
            ->with(\Mockery::on(fn ($arg) => $arg instanceof Trip && (int) $arg->id === (int) $trip->id));
        $this->app->instance(\STS\Services\Logic\ConversationsManager::class, $conversationManagerMock);

        $this->manager()->sendFullTripMessage($trip);
    }

    public function test_send_full_trip_message_skips_when_module_is_disabled(): void
    {
        Config::set('carpoolear.module_send_full_trip_message', false);
        $driver = User::factory()->create(['send_full_trip_message' => 1]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 1,
        ]);
        $passenger = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        $trip->load(['user', 'passengerAccepted']);

        $conversationManagerMock = \Mockery::mock(\STS\Services\Logic\ConversationsManager::class);
        $conversationManagerMock->shouldReceive('sendFullTripMessage')->never();
        $this->app->instance(\STS\Services\Logic\ConversationsManager::class, $conversationManagerMock);

        $this->manager()->sendFullTripMessage($trip);
    }

    public function test_pay_request_completes_waiting_payment_and_dispatches_reject_event(): void
    {
        Event::fake([RejectEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $out = $this->manager()->payRequest($trip->id, $passenger->id, $driver, []);

        $this->assertInstanceOf(Passenger::class, $out);
        $this->assertTrue((bool) $out);
        $this->assertSame(Passenger::STATE_ACCEPTED, (int) Passenger::where('trip_id', $trip->id)->where('user_id', $passenger->id)->value('request_state'));
        Event::assertDispatched(RejectEvent::class);
    }

    public function test_pay_request_returns_null_when_passenger_not_waiting_payment(): void
    {
        Event::fake([RejectEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passenger = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->payRequest($trip->id, $passenger->id, $driver, []));
        $this->assertSame('not_valid_request', $manager->getErrors()['error']);
        Event::assertNotDispatched(RejectEvent::class);
    }

    public function test_pay_request_returns_early_when_input_invalid(): void
    {
        Event::fake([RejectEvent::class]);
        $driver = User::factory()->create();

        $this->assertNull($this->manager()->payRequest(null, 1, $driver, []));
        Event::assertNotDispatched(RejectEvent::class);
    }

    public function test_reject_request_returns_early_when_input_invalid(): void
    {
        Event::fake([RejectEvent::class]);
        $driver = User::factory()->create();

        $this->assertNull($this->manager()->rejectRequest(null, 1, $driver, []));
        Event::assertNotDispatched(RejectEvent::class);
    }

    public function test_accept_request_returns_early_when_input_invalid(): void
    {
        Event::fake([AcceptEvent::class]);
        $driver = User::factory()->create();

        $this->assertNull($this->manager()->acceptRequest(null, 1, $driver, []));
        Event::assertNotDispatched(AcceptEvent::class);
    }

    public function test_transactions_returns_passengers_with_payment_status_on_past_trips(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $pastTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);
        $p = Passenger::factory()->aceptado()->create([
            'trip_id' => $pastTrip->id,
            'user_id' => $passenger->id,
        ]);
        $p->forceFill(['payment_status' => 'completed'])->saveQuietly();

        $tx = $this->manager()->transactions($driver);

        $this->assertCount(1, $tx);
        $this->assertSame('completed', $tx[0]->payment_status);
        $this->assertTrue($pastTrip->is($tx[0]->trip));
    }

    public function test_is_user_request_helpers_delegate_to_repository(): void
    {
        $trip = Trip::factory()->create();
        $user = User::factory()->create();
        $m = $this->manager();

        $this->assertFalse($m->isUserRequestPending($trip->id, $user->id));
        $this->assertFalse($m->isUserRequestAccepted($trip->id, $user->id));
        $this->assertFalse($m->isUserRequestRejected($trip->id, $user->id));
        $this->assertFalse($m->isUserRequestWaitingPayment($trip->id, $user->id));

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->assertTrue($m->isUserRequestPending($trip->id, $user->id));
    }
}
