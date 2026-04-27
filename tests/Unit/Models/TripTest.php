<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class TripTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($trip->fresh()->user->is($user));
    }

    public function test_state_helpers_and_setters(): void
    {
        $trip = Trip::factory()->create(['state' => Trip::STATE_READY]);

        $this->assertTrue($trip->fresh()->isReady());
        $this->assertFalse($trip->fresh()->isCanceled());

        $trip->fresh()->setStateCanceled()->save();
        $this->assertTrue($trip->fresh()->isCanceled());

        $trip->fresh()->setStateAwaitingPayment()->save();
        $this->assertTrue($trip->fresh()->isAwaitingPayment());

        $trip->fresh()->setStatePaymentFailed()->save();
        $this->assertTrue($trip->fresh()->isPaymentFailed());
    }

    public function test_expired_false_when_weekly_schedule_non_zero_even_if_trip_date_past(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $trip = Trip::factory()->create([
            'weekly_schedule' => Trip::DAY_MONDAY,
            'trip_date' => '2026-01-01 08:00:00',
        ]);

        $this->assertFalse($trip->fresh()->expired());
    }

    public function test_expired_false_when_trip_date_null(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $trip = Trip::factory()->create([
            'weekly_schedule' => 0,
            'trip_date' => null,
        ]);

        $this->assertFalse($trip->fresh()->expired());
    }

    public function test_expired_true_when_single_date_trip_in_past(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');

        $trip = Trip::factory()->create([
            'weekly_schedule' => 0,
            'trip_date' => '2026-08-10 10:00:00',
        ]);

        $this->assertTrue($trip->fresh()->expired());
    }

    public function test_seats_available_and_is_driver_appends(): void
    {
        $driver = User::factory()->create();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 5,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create(['user_id' => $p1->id, 'trip_id' => $trip->id]);
        Passenger::factory()->aceptado()->create(['user_id' => $p2->id, 'trip_id' => $trip->id]);

        $trip = $trip->fresh();
        $this->assertSame(3, $trip->seats_available);
        $this->assertTrue($trip->is_driver);

        $passengerTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => true,
        ]);
        $this->assertFalse($passengerTrip->fresh()->is_driver);
    }

    public function test_to_array_hides_enc_path(): void
    {
        $trip = Trip::factory()->create(['enc_path' => 'secret-polyline-token']);
        $array = $trip->fresh()->toArray();

        $this->assertArrayNotHasKey('enc_path', $array);
    }

    public function test_core_constants(): void
    {
        $this->assertSame(0, Trip::FINALIZADO);
        $this->assertSame(1, Trip::ACTIVO);
        $this->assertSame(2, Trip::PRIVACY_PUBLIC);
        $this->assertSame(0, Trip::PRIVACY_FRIENDS);
        $this->assertSame(1, Trip::PRIVACY_FOF);
        $this->assertSame('ready', Trip::STATE_READY);
        $this->assertSame(1, Trip::DAY_MONDAY);
        $this->assertSame(64, Trip::DAY_SUNDAY);
    }

    public function test_soft_delete_marks_trashed(): void
    {
        $trip = Trip::factory()->create();
        $trip->delete();

        $this->assertTrue($trip->fresh()->trashed());
    }
}
