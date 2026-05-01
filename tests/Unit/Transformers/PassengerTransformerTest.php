<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Transformers\PassengerTransformer;
use Tests\TestCase;

class PassengerTransformerTest extends TestCase
{
    public function test_transform_returns_expected_passenger_payload_shape(): void
    {
        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $passenger = Passenger::query()->create([
            'user_id' => $passengerUser->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_ACCEPTED,
            'canceled_state' => null,
        ]);
        $passenger->forceFill(['created_at' => Carbon::parse('2026-04-30 12:00:00')])->saveQuietly();

        $payload = (new PassengerTransformer($driver))->transform($passenger->fresh());

        $this->assertSame([
            'id',
            'trip_id',
            'created_at',
            'state',
            'user',
        ], array_keys($payload));
        $this->assertSame($passenger->id, $payload['id']);
        $this->assertSame($trip->id, $payload['trip_id']);
        $this->assertSame('2026-04-30 12:00:00', $payload['created_at']);
        $this->assertSame(Passenger::STATE_ACCEPTED, $payload['state']);
        $this->assertIsArray($payload['user']);
        $this->assertSame($passengerUser->id, $payload['user']['id']);
    }
}
