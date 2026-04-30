<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
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
        $this->assertSame(400, $payload['distance']);
        $this->assertSame(15000, $payload['seat_price_cents']);
        $this->assertFalse($payload['sellado_pending']);
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
        $this->assertSame('Falta pagar Sellado', $payload['sellado_pending_label']);
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
}
