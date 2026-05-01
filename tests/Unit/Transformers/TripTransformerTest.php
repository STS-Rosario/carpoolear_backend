<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Transformers\TripTransformer;
use Tests\TestCase;

class TripTransformerTest extends TestCase
{
    private function makeTrip(array $overrides = []): Trip
    {
        $owner = User::factory()->create();

        return Trip::query()->create(array_merge([
            'user_id' => $owner->id,
            'from_town' => 'Rosario',
            'to_town' => 'Cordoba',
            'trip_date' => '2026-04-30 18:30:00',
            'weekly_schedule' => 0,
            'weekly_schedule_time' => null,
            'description' => 'Intercity trip',
            'total_seats' => 4,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'distance' => 400,
            'estimated_time' => '05:30',
            'seat_price_cents' => 15000,
            'recommended_trip_price_cents' => 16000,
            'total_price' => 60000,
            'state' => Trip::STATE_READY,
            'is_passenger' => false,
            'allow_kids' => true,
            'allow_animals' => false,
            'allow_smoking' => false,
            'payment_id' => null,
            'needs_sellado' => false,
        ], $overrides));
    }

    public function test_transform_returns_expected_base_trip_payload_without_user_context(): void
    {
        $trip = $this->makeTrip();

        $payload = (new TripTransformer(null))->transform($trip->fresh());

        $this->assertSame([
            'id',
            'from_town',
            'to_town',
            'trip_date',
            'weekly_schedule',
            'weekly_schedule_time',
            'description',
            'total_seats',
            'friendship_type_id',
            'distance',
            'estimated_time',
            'seat_price_cents',
            'recommended_trip_price_cents',
            'total_price',
            'state',
            'is_passenger',
            'passenger_count',
            'seats_available',
            'points',
            'ratings',
            'updated_at',
            'allow_kids',
            'allow_animals',
            'allow_smoking',
            'payment_id',
            'needs_sellado',
            'sellado_pending',
            'sellado_pending_label',
            'request',
            'passenger',
        ], array_keys($payload));
        $this->assertSame('Rosario', $payload['from_town']);
        $this->assertSame('Cordoba', $payload['to_town']);
        $this->assertSame('2026-04-30 18:30:00', $payload['trip_date']);
        $this->assertEquals(400, $payload['distance']);
        $this->assertSame(15000, $payload['seat_price_cents']);
        $this->assertFalse($payload['sellado_pending']);
        $this->assertIsBool($payload['sellado_pending']);
        $this->assertNull($payload['sellado_pending_label']);
        $this->assertSame('', $payload['request']);
        $this->assertSame([], $payload['passenger']);
    }

    public function test_transform_marks_sellado_pending_when_needed_and_not_ready(): void
    {
        $trip = $this->makeTrip([
            'needs_sellado' => true,
            'state' => Trip::STATE_AWAITING_PAYMENT,
        ]);

        $payload = (new TripTransformer(null))->transform($trip->fresh());

        $this->assertTrue($payload['sellado_pending']);
        $this->assertIsBool($payload['sellado_pending']);
        $this->assertSame('Falta pagar Sellado', $payload['sellado_pending_label']);
    }

    public function test_transform_requires_needs_sellado_and_non_ready_state_for_pending_flag(): void
    {
        $trip = $this->makeTrip([
            'needs_sellado' => false,
            'state' => Trip::STATE_AWAITING_PAYMENT,
        ]);

        $payload = (new TripTransformer(null))->transform($trip->fresh());

        $this->assertFalse($payload['sellado_pending']);
        $this->assertIsBool($payload['sellado_pending']);
        $this->assertNull($payload['sellado_pending_label']);
    }

    public function test_transform_marks_hidden_or_deleted_based_on_deleted_at_value(): void
    {
        $hiddenTrip = $this->makeTrip();
        $hiddenTrip->forceFill(['deleted_at' => Carbon::parse('2000-01-01 00:00:00')])->saveQuietly();
        $hiddenPayload = (new TripTransformer(null))->transform($hiddenTrip->fresh());
        $this->assertSame('2000-01-01 00:00:00', $hiddenPayload['deleted_at']);
        $this->assertTrue($hiddenPayload['hidden']);
        $this->assertArrayNotHasKey('deleted', $hiddenPayload);

        $deletedTrip = $this->makeTrip();
        $deletedTrip->forceFill(['deleted_at' => Carbon::parse('2026-05-01 12:00:00')])->saveQuietly();
        $deletedPayload = (new TripTransformer(null))->transform($deletedTrip->fresh());
        $this->assertSame('2026-05-01 12:00:00', $deletedPayload['deleted_at']);
        $this->assertTrue($deletedPayload['deleted']);
        $this->assertArrayNotHasKey('hidden', $deletedPayload);
    }

    public function test_transform_includes_owner_context_passenger_data_and_counts(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $acceptedUser = User::factory()->create();
        $pendingUser = User::factory()->create();
        $trip = $this->makeTrip([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
        ]);

        Passenger::query()->create([
            'user_id' => $acceptedUser->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_ACCEPTED,
            'canceled_state' => null,
        ]);
        Passenger::query()->create([
            'user_id' => $pendingUser->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_PENDING,
            'canceled_state' => null,
        ]);

        $payload = (new TripTransformer($owner))->transform($trip->fresh());

        $this->assertArrayHasKey('allPassengerRequest', $payload);
        $this->assertArrayHasKey('request_count', $payload);
        $this->assertArrayHasKey('passengerAccepted_count', $payload);
        $this->assertArrayHasKey('passengerPending_count', $payload);
        $this->assertCount(1, $payload['passenger']);
        $this->assertSame($acceptedUser->id, $payload['passenger'][0]['id']);
        $this->assertSame(2, $payload['request_count']);
        $this->assertSame(1, $payload['passengerAccepted_count']);
        $this->assertSame(1, $payload['passengerPending_count']);
    }

    public function test_transform_sets_request_send_for_pending_non_owner_user(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $requester = User::factory()->create(['is_admin' => false]);
        $trip = $this->makeTrip([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
        ]);

        Passenger::query()->create([
            'user_id' => $requester->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_PENDING,
            'canceled_state' => null,
        ]);

        $payload = (new TripTransformer($requester))->transform($trip->fresh());

        $this->assertSame('send', $payload['request']);
        $this->assertArrayNotHasKey('allPassengerRequest', $payload);
        $this->assertSame(1, $payload['passengerPending_count']);
    }

    public function test_transform_owner_branch_matches_numeric_string_trip_user_id(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $acceptedUser = User::factory()->create();
        $trip = $this->makeTrip([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
        ]);

        Passenger::query()->create([
            'user_id' => $acceptedUser->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_ACCEPTED,
            'canceled_state' => null,
        ]);

        $trip = $trip->fresh(['user', 'passenger', 'passengerAccepted']);
        $trip->mergeCasts(['user_id' => 'string']);
        $trip->syncOriginal();
        $trip->forceFill(['user_id' => (string) $owner->id]);
        $trip->setRelation('user', $owner);

        $payload = (new TripTransformer($owner))->transform($trip);

        $this->assertArrayHasKey('allPassengerRequest', $payload);
        $this->assertCount(1, $payload['passenger']);
    }

    public function test_transform_mutates_pending_passenger_rows_with_request_metadata(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $pendingUser = User::factory()->create(['name' => 'Pending Rider', 'email' => 'pending@example.test']);
        $trip = $this->makeTrip([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
        ]);

        $pending = Passenger::query()->create([
            'user_id' => $pendingUser->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_PENDING,
            'canceled_state' => null,
        ]);

        $payload = (new TripTransformer($owner))->transform($trip->fresh());

        $this->assertGreaterThan(0, $payload['request_count']);
        $pendingRow = collect($payload['allPassengerRequest'])->firstWhere('id', $pendingUser->id);
        $this->assertNotNull($pendingRow);
        $this->assertSame($pending->id, $pendingRow->request_id);
        $this->assertSame($pendingUser->id, $pendingRow->id);
        $this->assertSame('Pending Rider', $pendingRow->name);
        $this->assertSame('pending@example.test', $pendingRow->email);
    }

    public function test_transform_admin_with_pending_request_gets_send_flag_without_owner_inner_branch(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $trip = $this->makeTrip([
            'user_id' => $owner->id,
            'state' => Trip::STATE_READY,
        ]);

        Passenger::query()->create([
            'user_id' => $admin->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_PENDING,
            'canceled_state' => null,
        ]);

        $payload = (new TripTransformer($admin))->transform($trip->fresh());

        $this->assertSame('send', $payload['request']);
        $this->assertArrayHasKey('allPassengerRequest', $payload);
    }
}
