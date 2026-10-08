<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use STS\Helpers\SelladoEmptyTripCredit;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class SelladoEmptyTripCreditTest extends TestCase
{
    private function paidFinishedEmptyTrip(User $user, array $overrides = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'user_id' => $user->id,
            'is_passenger' => false,
            'needs_sellado' => true,
            'state' => Trip::STATE_READY,
            'trip_date' => Carbon::now()->subDay(),
        ], $overrides));
    }

    public function test_user_has_credit_after_a_finished_paid_sellado_trip_with_no_passengers(): void
    {
        $user = User::factory()->create();
        $this->paidFinishedEmptyTrip($user);

        $this->assertTrue(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_user_has_no_credit_when_the_paid_sellado_trip_is_still_upcoming(): void
    {
        $user = User::factory()->create();
        $this->paidFinishedEmptyTrip($user, [
            'trip_date' => Carbon::now()->addDay(),
        ]);

        $this->assertFalse(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_user_has_no_credit_when_sellado_was_not_paid(): void
    {
        $user = User::factory()->create();
        $this->paidFinishedEmptyTrip($user, [
            'state' => Trip::STATE_AWAITING_PAYMENT,
        ]);

        $this->assertFalse(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_user_has_no_credit_when_the_trip_had_accepted_passengers(): void
    {
        $user = User::factory()->create();
        $trip = $this->paidFinishedEmptyTrip($user);
        $passenger = User::factory()->create();
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $this->assertFalse(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_pending_passenger_requests_do_not_consume_the_empty_trip_credit(): void
    {
        $user = User::factory()->create();
        $trip = $this->paidFinishedEmptyTrip($user);
        $requester = User::factory()->create();
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $requester->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $this->assertTrue(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_canceled_paid_trips_do_not_grant_credit(): void
    {
        $user = User::factory()->create();
        $this->paidFinishedEmptyTrip($user, [
            'state' => Trip::STATE_CANCELED,
        ]);

        $this->assertFalse(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_another_users_empty_paid_trip_does_not_grant_credit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->paidFinishedEmptyTrip($other);

        $this->assertFalse(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
    }

    public function test_redeeming_credit_prevents_using_the_same_empty_trip_again(): void
    {
        $user = User::factory()->create();
        $trip = $this->paidFinishedEmptyTrip($user);

        $redeemed = SelladoEmptyTripCredit::redeemOldestUnusedCredit($user->id);

        $this->assertSame($trip->id, $redeemed->id);
        $this->assertFalse(SelladoEmptyTripCredit::userHasUnusedCredit($user->id));
        $this->assertNull(SelladoEmptyTripCredit::redeemOldestUnusedCredit($user->id));
    }
}
