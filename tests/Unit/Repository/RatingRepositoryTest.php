<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\RatingRepository;
use Tests\TestCase;

class RatingRepositoryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedRating(User $from, User $to, Trip $trip, array $overrides = []): Rating
    {
        $rating = Rating::factory()->create(array_merge([
            'trip_id' => $trip->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'user_to_type' => 0,
            'user_to_state' => 0,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'ok',
            'reply_comment' => '',
            'reply_comment_created_at' => null,
            'voted' => true,
            'voted_hash' => 'h-'.uniqid('', true),
            'rate_at' => null,
        ], $overrides));

        $available = array_key_exists('available', $overrides)
            ? $overrides['available']
            : true;
        $rating->forceFill(['available' => $available])->saveQuietly();

        return $rating->fresh();
    }

    public function test_get_rating_returns_row_for_from_to_trip(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $expected = $this->seedRating($passenger, $driver, $trip);
        // Noise rows to keep where('user_id_to') and where('trip_id') mandatory.
        $this->seedRating($passenger, User::factory()->create(), $trip, ['voted_hash' => 'noise-to']);
        $this->seedRating($passenger, $driver, Trip::factory()->create(['user_id' => $driver->id]), ['voted_hash' => 'noise-trip']);

        $repo = new RatingRepository;
        $found = $repo->getRating($passenger->id, $driver->id, $trip->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($expected));
        $this->assertSame($passenger->id, (int) $found->user_id_from);
        $this->assertSame($driver->id, (int) $found->user_id_to);
        $this->assertSame($trip->id, (int) $found->trip_id);
    }

    public function test_create_persists_pending_rating_shape(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;

        $row = $repo->create($passenger->id, $driver->id, $trip->id, 1, 2, 'hash-abc');

        $row = $row->fresh();
        $this->assertSame($passenger->id, (int) $row->user_id_from);
        $this->assertSame($driver->id, (int) $row->user_id_to);
        $this->assertSame($trip->id, (int) $row->trip_id);
        $this->assertNull($row->rating);
        $this->assertFalse((bool) $row->voted);
        $this->assertSame('hash-abc', $row->voted_hash);
        $this->assertSame(1, (int) $row->user_to_type);
        $this->assertSame(2, (int) $row->user_to_state);
        $this->assertSame('', $row->comment);
        $this->assertSame('', $row->reply_comment);
        $this->assertNull($row->reply_comment_created_at);
        $this->assertNull($row->rate_at);
    }

    public function test_find_and_find_by(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $rating = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'unique-find-hash']);

        $repo = new RatingRepository;
        $this->assertTrue($rating->is($repo->find($rating->id)));

        $byHash = $repo->findBy('voted_hash', 'unique-find-hash');
        $this->assertCount(1, $byHash);
        $this->assertTrue($rating->is($byHash->first()));
    }

    public function test_update_persists_changes(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $rating = $this->seedRating($passenger, $driver, $trip);

        $rating->comment = 'Updated via repository.';
        $repo = new RatingRepository;
        $this->assertTrue($repo->update($rating));

        $this->assertSame('Updated via repository.', $rating->fresh()->comment);
    }

    public function test_get_pending_ratings_filters_by_user_voted_and_recency(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);
        $repo = new RatingRepository;

        $recent = $repo->create($user->id, $other->id, $trip->id, 0, 0, 'r1-'.uniqid('', true));
        $old = $repo->create($user->id, $other->id, $trip->id, 0, 0, 'r2-'.uniqid('', true));
        $old->forceFill(['created_at' => '2026-05-01 00:00:00'])->saveQuietly();
        // Must be excluded by where('voted', false).
        $voted = $repo->create($user->id, $other->id, $trip->id, 0, 0, 'r3-'.uniqid('', true));
        $voted->forceFill(['voted' => true])->saveQuietly();

        $listed = $repo->getPendingRatings($user);
        $this->assertCount(1, $listed);
        $this->assertTrue($listed->first()->is($recent));
        $this->assertTrue($listed->first()->relationLoaded('from'));
        $this->assertTrue($listed->first()->relationLoaded('to'));
        $this->assertTrue($listed->first()->relationLoaded('trip'));

        Carbon::setTestNow();
    }

    public function test_get_ratings_filters_available_and_value_when_page_size_null(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->seedRating($passenger, $driver, $trip, [
            'rating' => Rating::STATE_POSITIVO,
            'available' => true,
        ]);
        $this->seedRating($passenger, $driver, $trip, [
            'rating' => Rating::STATE_NEGATIVO,
            'voted_hash' => 'neg-'.uniqid('', true),
            'available' => true,
        ]);
        $this->seedRating($passenger, $driver, $trip, [
            'rating' => Rating::STATE_POSITIVO,
            'voted_hash' => 'hidden-'.uniqid('', true),
            'available' => false,
        ]);

        $repo = new RatingRepository;
        $positive = $repo->getRatings($driver, ['value' => 'true']);
        $this->assertCount(1, $positive);
        $this->assertSame(Rating::STATE_POSITIVO, (int) $positive->first()->rating);

        $allAvailable = $repo->getRatings($driver, []);
        $this->assertCount(2, $allAvailable);
    }

    public function test_get_ratings_orders_by_created_at_desc(): void
    {
        // Mutation intent: preserve orderBy('created_at', 'desc').
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $older = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'ord-old']);
        $newer = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'ord-new']);
        $older->forceFill(['created_at' => Carbon::parse('2025-01-01 10:00:00')])->saveQuietly();
        $newer->forceFill(['created_at' => Carbon::parse('2025-01-02 10:00:00')])->saveQuietly();

        $rows = (new RatingRepository)->getRatings($driver, []);

        $this->assertGreaterThanOrEqual(2, $rows->count());
        $this->assertSame($newer->id, $rows->first()->id);
    }

    public function test_get_ratings_paginates_when_page_size_provided(): void
    {
        // Mutation intent: preserve make_pagination branch (~40–43) when page_size non-null (distinct from plain Collection).
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;

        $rNewest = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'pag-a-'.uniqid('', true)]);
        $rNewest->forceFill(['created_at' => Carbon::parse('2025-03-04 10:00:00')])->saveQuietly();
        $rB = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'pag-b-'.uniqid('', true)]);
        $rB->forceFill(['created_at' => Carbon::parse('2025-03-03 10:00:00')])->saveQuietly();
        $rC = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'pag-c-'.uniqid('', true)]);
        $rC->forceFill(['created_at' => Carbon::parse('2025-03-02 10:00:00')])->saveQuietly();
        $rOldest = $this->seedRating($passenger, $driver, $trip, ['voted_hash' => 'pag-d-'.uniqid('', true)]);
        $rOldest->forceFill(['created_at' => Carbon::parse('2025-03-01 10:00:00')])->saveQuietly();

        $page1 = $repo->getRatings($driver, ['page' => 1, 'page_size' => 2]);
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $page1);
        $this->assertCount(2, $page1);
        $this->assertSame(4, $page1->total());
        $this->assertSame($rNewest->id, $page1->first()->id);

        $page2 = $repo->getRatings($driver, ['page' => 2, 'page_size' => 2]);
        $this->assertCount(2, $page2);
        $this->assertSame([$rC->id, $rOldest->id], $page2->pluck('id')->all());
    }
}
