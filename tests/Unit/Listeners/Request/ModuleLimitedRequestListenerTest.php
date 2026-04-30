<?php

namespace Tests\Unit\Listeners\Request;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use STS\Events\Passenger\Accept as PassengerAccepted;
use STS\Events\Passenger\AutoCancel;
use STS\Listeners\Request\ModuleLimitedRequest;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class ModuleLimitedRequestListenerTest extends TestCase
{
    public function test_handle_does_nothing_when_request_limit_module_is_disabled(): void
    {
        config([
            'carpoolear.module_user_request_limited_enabled' => false,
        ]);

        Event::fake([AutoCancel::class]);

        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'SharedTown',
            'trip_date' => Carbon::parse('2030-06-01 10:00:00'),
        ]);

        (new ModuleLimitedRequest)->handle(new PassengerAccepted($trip, $driver, $passenger));

        Event::assertNotDispatched(AutoCancel::class);
    }

    public function test_handle_does_nothing_when_module_enabled_config_key_is_absent(): void
    {
        $carpoolear = config('carpoolear');
        unset($carpoolear['module_user_request_limited_enabled']);
        config(['carpoolear' => $carpoolear]);

        Event::fake([AutoCancel::class]);

        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'SharedTown',
            'trip_date' => Carbon::parse('2030-06-01 10:00:00'),
        ]);

        (new ModuleLimitedRequest)->handle(new PassengerAccepted($trip, $driver, $passenger));

        Event::assertNotDispatched(AutoCancel::class);
    }

    public function test_handle_dispatches_auto_cancel_and_marks_other_pending_request_when_module_enabled_and_destinations_align(): void
    {
        config([
            'carpoolear.module_user_request_limited_enabled' => true,
            'carpoolear.module_user_request_limited_hours_range' => 2,
        ]);

        Event::fake([AutoCancel::class]);

        $driverA = User::factory()->create();
        $driverB = User::factory()->create();
        $passenger = User::factory()->create();

        $base = Carbon::parse('2030-06-10 10:00:00');

        $acceptedTrip = Trip::factory()->create([
            'user_id' => $driverA->id,
            'to_town' => 'Cordoba Centro',
            'trip_date' => $base->copy(),
        ]);

        $otherTrip = Trip::factory()->create([
            'user_id' => $driverB->id,
            'to_town' => 'Cordoba Centro',
            'trip_date' => $base->copy()->addHour(),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $acceptedTrip->id,
            'user_id' => $passenger->id,
        ]);

        $pendingElsewhere = Passenger::factory()->create([
            'trip_id' => $otherTrip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        (new ModuleLimitedRequest)->handle(new PassengerAccepted($acceptedTrip->fresh(), $driverA, $passenger->fresh()));

        Event::assertDispatched(AutoCancel::class, function (AutoCancel $event) use ($otherTrip, $driverB, $passenger) {
            return $event->trip->is($otherTrip)
                && $event->from->is($driverB)
                && $event->to->is($passenger);
        });

        $this->assertSame(Passenger::STATE_CANCELED, $pendingElsewhere->fresh()->request_state);
        $this->assertSame(Passenger::CANCELED_SYSTEM, $pendingElsewhere->fresh()->canceled_state);
    }

    public function test_handle_skips_when_other_pending_trip_has_different_destination(): void
    {
        config([
            'carpoolear.module_user_request_limited_enabled' => true,
            'carpoolear.module_user_request_limited_hours_range' => 2,
        ]);

        Event::fake([AutoCancel::class]);

        $driverA = User::factory()->create();
        $driverB = User::factory()->create();
        $passenger = User::factory()->create();

        $base = Carbon::parse('2030-07-01 09:00:00');

        $acceptedTrip = Trip::factory()->create([
            'user_id' => $driverA->id,
            'to_town' => 'Mendoza',
            'trip_date' => $base->copy(),
        ]);

        $otherTrip = Trip::factory()->create([
            'user_id' => $driverB->id,
            'to_town' => 'San Juan',
            'trip_date' => $base->copy()->addMinutes(30),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $acceptedTrip->id,
            'user_id' => $passenger->id,
        ]);

        Passenger::factory()->create([
            'trip_id' => $otherTrip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        (new ModuleLimitedRequest)->handle(new PassengerAccepted($acceptedTrip->fresh(), $driverA, $passenger->fresh()));

        Event::assertNotDispatched(AutoCancel::class);
    }

    public function test_handle_skips_when_other_pending_trip_is_outside_configured_hour_window(): void
    {
        config([
            'carpoolear.module_user_request_limited_enabled' => true,
            'carpoolear.module_user_request_limited_hours_range' => 2,
        ]);

        Event::fake([AutoCancel::class]);

        $driverA = User::factory()->create();
        $driverB = User::factory()->create();
        $passenger = User::factory()->create();

        $base = Carbon::parse('2030-08-01 12:00:00');

        $acceptedTrip = Trip::factory()->create([
            'user_id' => $driverA->id,
            'to_town' => 'Rosario',
            'trip_date' => $base->copy(),
        ]);

        $otherTrip = Trip::factory()->create([
            'user_id' => $driverB->id,
            'to_town' => 'Rosario',
            'trip_date' => $base->copy()->addHours(5),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $acceptedTrip->id,
            'user_id' => $passenger->id,
        ]);

        Passenger::factory()->create([
            'trip_id' => $otherTrip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        (new ModuleLimitedRequest)->handle(new PassengerAccepted($acceptedTrip->fresh(), $driverA, $passenger->fresh()));

        Event::assertNotDispatched(AutoCancel::class);
    }

    public function test_handle_uses_two_hours_as_default_window_when_hours_range_config_is_absent(): void
    {
        $carpoolear = config('carpoolear');
        unset($carpoolear['module_user_request_limited_hours_range']);
        $carpoolear['module_user_request_limited_enabled'] = true;
        config(['carpoolear' => $carpoolear]);

        Event::fake([AutoCancel::class]);

        $driverA = User::factory()->create();
        $driverB = User::factory()->create();
        $passenger = User::factory()->create();

        $base = Carbon::parse('2030-09-01 12:00:00');

        $acceptedTrip = Trip::factory()->create([
            'user_id' => $driverA->id,
            'to_town' => 'Rosario',
            'trip_date' => $base->copy(),
        ]);

        $otherTrip = Trip::factory()->create([
            'user_id' => $driverB->id,
            'to_town' => 'Rosario',
            'trip_date' => $base->copy()->addHours(3),
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $acceptedTrip->id,
            'user_id' => $passenger->id,
        ]);

        $otherRequest = Passenger::factory()->create([
            'trip_id' => $otherTrip->id,
            'user_id' => $passenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        (new ModuleLimitedRequest)->handle(new PassengerAccepted($acceptedTrip->fresh(), $driverA, $passenger->fresh()));

        Event::assertNotDispatched(AutoCancel::class);
        $this->assertSame(Passenger::STATE_PENDING, $otherRequest->fresh()->request_state);
    }
}
