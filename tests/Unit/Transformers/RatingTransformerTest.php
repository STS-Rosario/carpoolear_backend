<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Transformers\RatingTransformer;
use Tests\TestCase;

class RatingTransformerTest extends TestCase
{
    public function test_transform_includes_expected_payload_keys_when_rate_is_pending(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $to->id]);

        $rate = Rating::query()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Great trip',
            'reply_comment' => 'Thanks!',
            'reply_comment_created_at' => '2026-04-29 08:00:00',
            'user_to_type' => 1,
            'user_to_state' => 1,
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => null,
        ]);

        $payload = (new RatingTransformer($from))->transform($rate->fresh());

        $this->assertSame([
            'id',
            'from',
            'trip',
            'comment',
            'user_to_state',
            'user_to_type',
            'rate_at',
            'reply_comment',
            'reply_comment_created_at',
            'rating',
            'to',
        ], array_keys($payload));
        $this->assertSame($rate->id, $payload['id']);
        $this->assertSame('Great trip', $payload['comment']);
        $this->assertSame($trip->id, $payload['trip']['id']);
        $this->assertNull($payload['rate_at']);
        $this->assertSame('2026-04-29 08:00:00', $payload['reply_comment_created_at']);
        $this->assertSame($to->id, $payload['to']['id']);
    }

    public function test_transform_omits_to_when_rate_at_exists_and_formats_dates(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $to->id]);

        $rate = Rating::query()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'Late arrival',
            'reply_comment' => null,
            'reply_comment_created_at' => null,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::parse('2026-04-30 11:00:00'),
        ]);

        $payload = (new RatingTransformer($from))->transform($rate->fresh());

        $this->assertArrayNotHasKey('to', $payload);
        $this->assertSame('2026-04-30 11:00:00', $payload['rate_at']);
        $this->assertNull($payload['reply_comment_created_at']);
        $this->assertSame(Rating::STATE_NEGATIVO, $payload['rating']);
    }
}
