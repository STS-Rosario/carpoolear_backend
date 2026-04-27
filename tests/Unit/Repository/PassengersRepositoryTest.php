<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\PassengersRepository;
use Tests\TestCase;

class PassengersRepositoryTest extends TestCase
{
    private function repo(): PassengersRepository
    {
        return new PassengersRepository;
    }

    public function test_new_request_creates_pending_passenger_row(): void
    {
        $trip = Trip::factory()->create();
        $user = User::factory()->create();

        $p = $this->repo()->newRequest($trip->id, $user, []);

        $this->assertNotNull($p->id);
        $this->assertSame(Passenger::STATE_PENDING, (int) $p->request_state);
        $this->assertSame(Passenger::TYPE_PASAJERO, (int) $p->passenger_type);
        $this->assertSame($trip->id, $p->trip_id);
        $this->assertSame($user->id, $p->user_id);
    }

    public function test_accept_request_updates_pending_passenger(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $row = $this->repo()->acceptRequest($trip->id, $passenger->id, $driver, []);

        $this->assertSame(Passenger::STATE_ACCEPTED, (int) $row->fresh()->request_state);
    }

    public function test_reject_request_updates_pending_passenger(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $row = $this->repo()->rejectRequest($trip->id, $passenger->id, $driver, []);

        $this->assertSame(Passenger::STATE_REJECTED, (int) $row->fresh()->request_state);
    }

    public function test_approve_for_payment_then_pay_request(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);
        $repo = $this->repo();

        $waiting = $repo->aproveForPaymentRequest($trip->id, $passenger->id, $driver, []);
        $this->assertSame(Passenger::STATE_WAITING_PAYMENT, (int) $waiting->fresh()->request_state);

        $paid = $repo->payRequest($trip->id, $passenger->id, $driver, []);
        $this->assertSame(Passenger::STATE_ACCEPTED, (int) $paid->fresh()->request_state);
    }

    public function test_change_request_state_returns_null_when_no_matching_passenger(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        $passenger = User::factory()->create();

        $row = $this->repo()->acceptRequest($trip->id, $passenger->id, $driver, []);

        $this->assertNull($row);
    }

    public function test_cancel_request_pending_as_canceled_request(): void
    {
        $trip = Trip::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $row = $this->repo()->cancelRequest($trip->id, $passenger, Passenger::CANCELED_REQUEST);

        $this->assertSame(Passenger::STATE_CANCELED, (int) $row->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_REQUEST, (int) $row->fresh()->canceled_state);
    }

    public function test_cancel_request_while_paying_uses_waiting_payment_criteria(): void
    {
        $trip = Trip::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $row = $this->repo()->cancelRequest($trip->id, $passenger, Passenger::CANCELED_PASSENGER_WHILE_PAYING);

        $this->assertSame(Passenger::STATE_CANCELED, (int) $row->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_PASSENGER_WHILE_PAYING, (int) $row->fresh()->canceled_state);
    }

    public function test_cancel_request_from_accepted_uses_default_criteria(): void
    {
        $trip = Trip::factory()->create();
        $passenger = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_ACCEPTED,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $row = $this->repo()->cancelRequest($trip->id, $passenger, Passenger::CANCELED_DRIVER);

        $this->assertSame(Passenger::STATE_CANCELED, (int) $row->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_DRIVER, (int) $row->fresh()->canceled_state);
    }

    public function test_get_passengers_only_accepted_and_paginates(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $u1->id]);
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $u2->id]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $page = $this->repo()->getPassengers($trip->id, $driver, ['page' => 1, 'page_size' => 1]);
        $this->assertCount(1, $page->items());

        $all = $this->repo()->getPassengers($trip->id, $driver, []);
        $this->assertCount(2, $all);
    }

    public function test_get_pending_requests_for_trip_id(): void
    {
        $trip = Trip::factory()->create(['trip_date' => Carbon::now()->addDay()]);
        $driver = User::factory()->create();
        $pUser = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $pUser->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $rows = $this->repo()->getPendingRequests($trip->id, $driver, []);

        $this->assertCount(1, $rows);
        $this->assertSame($pUser->id, $rows->first()->user_id);
    }

    public function test_get_pending_requests_without_trip_id_scopes_to_drivers_future_trips(): void
    {
        $driver = User::factory()->create();
        $futureTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);
        $pastTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);
        $otherDriverTrip = Trip::factory()->create([
            'user_id' => User::factory()->create()->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);

        Passenger::factory()->create([
            'trip_id' => $futureTrip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        Passenger::factory()->create([
            'trip_id' => $pastTrip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        Passenger::factory()->create([
            'trip_id' => $otherDriverTrip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $rows = $this->repo()->getPendingRequests(null, $driver, []);

        $this->assertCount(1, $rows);
        $this->assertSame($futureTrip->id, $rows->first()->trip_id);
    }

    public function test_get_pending_payment_requests_for_user_on_future_trips(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $futureTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        Passenger::factory()->create([
            'trip_id' => $futureTrip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
        ]);

        $rows = $this->repo()->getPendingPaymentRequests(null, $passenger, []);

        $this->assertCount(1, $rows);
        $this->assertSame($futureTrip->id, $rows->first()->trip_id);
    }

    public function test_user_has_active_request_and_is_user_request_helpers(): void
    {
        $trip = Trip::factory()->create();
        $user = User::factory()->create();
        $repo = $this->repo();

        $this->assertFalse($repo->userHasActiveRequest($trip->id, $user->id));
        $this->assertFalse($repo->isUserRequestPending($trip->id, $user->id));

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        $this->assertTrue($repo->userHasActiveRequest($trip->id, $user->id));
        $this->assertTrue($repo->isUserRequestPending($trip->id, $user->id));
        $this->assertFalse($repo->isUserRequestAccepted($trip->id, $user->id));

        Passenger::where('trip_id', $trip->id)->where('user_id', $user->id)->update([
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);
        $this->assertTrue($repo->userHasActiveRequest($trip->id, $user->id));
        $this->assertTrue($repo->isUserRequestAccepted($trip->id, $user->id));
        $this->assertFalse($repo->isUserRequestPending($trip->id, $user->id));

        Passenger::where('trip_id', $trip->id)->where('user_id', $user->id)->update([
            'request_state' => Passenger::STATE_REJECTED,
        ]);
        $this->assertFalse($repo->userHasActiveRequest($trip->id, $user->id));
        $this->assertTrue($repo->isUserRequestRejected($trip->id, $user->id));
    }

    public function test_trips_with_transactions_returns_distinct_past_trips_with_payment_status(): void
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

        $trips = $this->repo()->tripsWithTransactions($driver);
        $this->assertCount(1, $trips);
        $this->assertSame($pastTrip->id, $trips->first()->id);

        $tripsAsPassenger = $this->repo()->tripsWithTransactions($passenger);
        $this->assertCount(1, $tripsAsPassenger);
        $this->assertSame($pastTrip->id, $tripsAsPassenger->first()->id);
    }

    public function test_trips_with_transactions_excludes_future_trips_and_rows_without_payment_status(): void
    {
        $driver = User::factory()->create();
        $futureTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addWeek(),
        ]);
        $pastNoPayment = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subDay(),
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $futureTrip->id,
            'user_id' => User::factory()->create()->id,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $pastNoPayment->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertCount(0, $this->repo()->tripsWithTransactions($driver));
    }

    public function test_state_changes_ignore_non_pasajero_rows(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        $user = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_CONDUCTOR,
        ]);

        $this->assertNull($this->repo()->acceptRequest($trip->id, $user->id, $driver, []));
    }
}
