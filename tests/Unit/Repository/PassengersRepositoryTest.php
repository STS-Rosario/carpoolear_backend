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

        $this->assertDatabaseHas('trip_passengers', [
            'id' => $p->id,
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);
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

    public function test_cancel_request_matches_reason_via_loose_equality_for_string_literals(): void
    {
        // Mutation intent: preserve `$canceledState == Passenger::...` branches (`EqualToIdentical` would reject `'0'` / `'3'` vs ints).
        $tripPending = Trip::factory()->create();
        $passengerPending = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $tripPending->id,
            'user_id' => $passengerPending->id,
            'request_state' => Passenger::STATE_PENDING,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $rowPending = $this->repo()->cancelRequest($tripPending->id, $passengerPending, (string) Passenger::CANCELED_REQUEST);
        $this->assertSame(Passenger::STATE_CANCELED, (int) $rowPending->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_REQUEST, (int) $rowPending->fresh()->canceled_state);

        $tripPay = Trip::factory()->create();
        $passengerPay = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $tripPay->id,
            'user_id' => $passengerPay->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $rowPay = $this->repo()->cancelRequest($tripPay->id, $passengerPay, (string) Passenger::CANCELED_PASSENGER_WHILE_PAYING);
        $this->assertSame(Passenger::STATE_CANCELED, (int) $rowPay->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_PASSENGER_WHILE_PAYING, (int) $rowPay->fresh()->canceled_state);
    }

    public function test_get_pending_requests_without_trip_id_excludes_soft_deleted_trips(): void
    {
        // Mutation intent: preserve `join('trips')` + `whereNull('trips.deleted_at')` when `$tripId` is falsy (RemoveMethodCall cluster).
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        $trip->delete();

        $this->assertCount(0, $this->repo()->getPendingRequests(null, $driver, []));
    }

    public function test_get_pending_payment_requests_excludes_soft_deleted_trips(): void
    {
        // Mutation intent: preserve trip join + soft-delete guard for passenger pending-payment listings.
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
        ]);
        $trip->delete();

        $this->assertCount(0, $this->repo()->getPendingPaymentRequests(null, $passenger, []));
    }

    public function test_user_has_active_request_includes_waiting_payment_state(): void
    {
        // Mutation intent: preserve `whereIn('request_state', [WAITING_PAYMENT, ACCEPTED, PENDING])`.
        $trip = Trip::factory()->create();
        $user = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_WAITING_PAYMENT,
        ]);

        $this->assertTrue($this->repo()->userHasActiveRequest($trip->id, $user->id));
    }

    public function test_get_passengers_returns_empty_when_trip_has_no_accepted_passengers(): void
    {
        // Mutation intent: preserve `whereIn('request_state', [ACCEPTED])` vs pending-only rows (~13–20).
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $rows = $this->repo()->getPassengers($trip->id, $driver, []);

        $this->assertCount(0, $rows);
    }

    public function test_get_passengers_paginates_empty_when_no_accepted_passengers(): void
    {
        $trip = Trip::factory()->create();
        $driver = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $page = $this->repo()->getPassengers($trip->id, $driver, ['page' => 1, 'page_size' => 10]);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $page);
        $this->assertCount(0, $page);
        $this->assertSame(0, $page->total());
    }

    public function test_get_pending_requests_for_trip_returns_empty_when_no_pending_rows(): void
    {
        // Mutation intent: preserve `where('request_state', PENDING)` on scoped trip (~25–39).
        $trip = Trip::factory()->create(['trip_date' => Carbon::now()->addDay()]);
        $driver = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertCount(0, $this->repo()->getPendingRequests($trip->id, $driver, []));
    }

    public function test_get_pending_payment_requests_returns_empty_when_user_has_no_waiting_payment_rows(): void
    {
        // Mutation intent: empty listing when passenger has no STATE_WAITING_PAYMENT rows on future trips (~49–63).
        $passenger = User::factory()->create();

        $this->assertCount(0, $this->repo()->getPendingPaymentRequests(null, $passenger, []));
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
