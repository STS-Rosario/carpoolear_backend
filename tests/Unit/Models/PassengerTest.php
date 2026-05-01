<?php

namespace Tests\Unit\Models;

use ReflectionMethod;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PassengerTest extends TestCase
{
    public function test_belongs_to_user_and_trip(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertTrue($passenger->user->is($passengerUser));
        $this->assertTrue($passenger->trip->is($trip));
    }

    public function test_trip_id_casts_to_integer(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertIsInt($passenger->fresh()->trip_id);
        $this->assertSame($trip->id, $passenger->trip_id);
    }

    public function test_payment_info_casts_to_array_when_set_on_model(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
        ]);

        $payload = ['provider' => 'mp', 'preference_id' => 'pref-123'];
        $passenger->payment_info = $payload;
        $passenger->saveQuietly();

        $passenger = $passenger->fresh();
        $this->assertSame($payload, $passenger->payment_info);
        $this->assertIsArray($passenger->payment_info);
    }

    public function test_persists_request_passenger_type_and_canceled_state(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::factory()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
            'request_state' => Passenger::STATE_ACCEPTED,
            'passenger_type' => Passenger::TYPE_CONDUCTOR,
            'canceled_state' => Passenger::CANCELED_DRIVER,
        ]);

        $passenger = $passenger->fresh();
        $this->assertSame(Passenger::STATE_ACCEPTED, $passenger->request_state);
        $this->assertSame(Passenger::TYPE_CONDUCTOR, $passenger->passenger_type);
        $this->assertSame(Passenger::CANCELED_DRIVER, $passenger->canceled_state);
    }

    public function test_state_type_and_cancel_constants(): void
    {
        $this->assertSame(0, Passenger::STATE_PENDING);
        $this->assertSame(1, Passenger::STATE_ACCEPTED);
        $this->assertSame(2, Passenger::STATE_REJECTED);
        $this->assertSame(3, Passenger::STATE_CANCELED);
        $this->assertSame(4, Passenger::STATE_WAITING_PAYMENT);

        $this->assertSame(0, Passenger::CANCELED_REQUEST);
        $this->assertSame(1, Passenger::CANCELED_DRIVER);
        $this->assertSame(2, Passenger::CANCELED_PASSENGER);
        $this->assertSame(3, Passenger::CANCELED_PASSENGER_WHILE_PAYING);
        $this->assertSame(4, Passenger::CANCELED_SYSTEM);

        $this->assertSame(0, Passenger::TYPE_CONDUCTOR);
        $this->assertSame(1, Passenger::TYPE_PASAJERO);
        $this->assertSame(2, Passenger::TYPE_CONDUCTORRECURRENTE);
    }

    public function test_table_name_is_trip_passengers(): void
    {
        $this->assertSame('trip_passengers', (new Passenger)->getTable());
    }

    public function test_casts_method_declares_trip_id_integer_and_payment_info_array(): void
    {
        $method = new ReflectionMethod(Passenger::class, 'casts');
        $method->setAccessible(true);
        $casts = $method->invoke(new Passenger);

        $this->assertSame([
            'trip_id' => 'integer',
            'payment_info' => 'array',
        ], $casts);
    }
}
