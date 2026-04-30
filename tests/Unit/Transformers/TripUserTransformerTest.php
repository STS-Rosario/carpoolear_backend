<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use STS\Models\User;
use STS\Transformers\TripUserTransformer;
use Tests\TestCase;

class TripUserTransformerTest extends TestCase
{
    public function test_transform_returns_expected_trip_user_payload_shape_and_values(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create([
            'name' => 'Trip User',
            'image' => 'trip-user.png',
            'last_connection' => '2026-04-30 15:00:00',
        ]);
        $user->forceFill([
            'descripcion' => 'Profile description',
            'private_note' => 'Private note',
            'positive_ratings' => 10,
            'negative_ratings' => 1,
            'accounts' => null,
            'has_pin' => true,
            'is_member' => false,
            'monthly_donate' => true,
            'do_not_alert_request_seat' => false,
            'do_not_alert_accept_passenger' => true,
            'do_not_alert_pending_rates' => false,
            'do_not_alert_pricing' => true,
            'autoaccept_requests' => false,
            'driver_is_verified' => true,
            'driver_data_docs' => json_encode(['dni' => true, 'license' => false]),
            'conversation_opened_count' => 3,
            'conversation_answered_count' => 2,
            'answer_delay_sum' => 120,
            'identity_validated_at' => Carbon::parse('2026-04-29 09:30:00'),
        ])->saveQuietly();

        $payload = (new TripUserTransformer($viewer))->transform($user->fresh());

        $this->assertSame([
            'id',
            'name',
            'descripcion',
            'private_note',
            'image',
            'positive_ratings',
            'negative_ratings',
            'last_connection',
            'accounts',
            'has_pin',
            'is_member',
            'monthly_donate',
            'do_not_alert_request_seat',
            'do_not_alert_accept_passenger',
            'do_not_alert_pending_rates',
            'do_not_alert_pricing',
            'autoaccept_requests',
            'driver_is_verified',
            'driver_data_docs',
            'conversation_opened_count',
            'conversation_answered_count',
            'answer_delay_sum',
            'identity_validated_at',
        ], array_keys($payload));
        $this->assertSame($user->id, $payload['id']);
        $this->assertSame('Trip User', $payload['name']);
        $this->assertSame('2026-04-30 15:00:00', $payload['last_connection']);
        $this->assertSame('2026-04-29 09:30:00', $payload['identity_validated_at']);
        $this->assertIsObject($payload['driver_data_docs']);
    }

    public function test_transform_returns_null_for_optional_docs_and_identity_date_when_absent(): void
    {
        $viewer = User::factory()->create();
        $user = User::factory()->create([
            'last_connection' => '2026-04-30 16:00:00',
        ]);
        $user->forceFill([
            'driver_data_docs' => null,
            'identity_validated_at' => null,
        ])->saveQuietly();

        $payload = (new TripUserTransformer($viewer))->transform($user->fresh());

        $this->assertNull($payload['driver_data_docs']);
        $this->assertNull($payload['identity_validated_at']);
    }
}
