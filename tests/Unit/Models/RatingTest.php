<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class RatingTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRating(User $from, User $to, Trip $trip, array $overrides = []): Rating
    {
        $rating = Rating::factory()->create(array_merge([
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Thanks for the ride.',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => false,
            'voted_hash' => 'test-hash-'.uniqid('', true),
            'rate_at' => null,
        ], $overrides));

        if (array_key_exists('available', $overrides)) {
            $rating->forceFill(['available' => $overrides['available']])->saveQuietly();
        } else {
            $rating->forceFill(['available' => true])->saveQuietly();
        }

        return $rating->fresh();
    }

    public function test_from_to_and_trip_relationships(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $rating = $this->makeRating($passenger, $driver, $trip);

        $this->assertTrue($rating->from->is($passenger));
        $this->assertTrue($rating->to->is($driver));
        $this->assertTrue($rating->trip->is($trip));
    }

    public function test_rate_at_and_reply_comment_created_at_cast_to_carbon(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $rating = $this->makeRating($passenger, $driver, $trip, [
            'rate_at' => '2026-04-10 14:30:00',
            'reply_comment_created_at' => '2026-04-11 08:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $rating->rate_at);
        $this->assertSame('2026-04-10 14:30:00', $rating->rate_at->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Carbon::class, $rating->reply_comment_created_at);
        $this->assertSame('2026-04-11 08:00:00', $rating->reply_comment_created_at->format('Y-m-d H:i:s'));
    }

    public function test_trip_relation_resolves_when_trip_is_soft_deleted(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $rating = $this->makeRating($passenger, $driver, $trip);

        $trip->delete();

        $loaded = Rating::query()->with('trip')->findOrFail($rating->id);
        $this->assertNotNull($loaded->trip);
        $this->assertTrue($loaded->trip->trashed());
        $this->assertSame($trip->id, $loaded->trip->id);
    }

    public function test_rating_state_constants(): void
    {
        $this->assertSame(0, Rating::STATE_NEGATIVO);
        $this->assertSame(1, Rating::STATE_POSITIVO);
        $this->assertSame(25, Rating::RATING_INTERVAL);
    }

    public function test_uses_rating_table_name(): void
    {
        $this->assertSame('rating', (new Rating)->getTable());
    }
}
