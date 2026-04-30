<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class RatingApiTest extends TestCase
{
    private function persistRating(array $attributes): Rating
    {
        $rating = new Rating;
        foreach ($attributes as $key => $value) {
            $rating->{$key} = $value;
        }
        if (! array_key_exists('available', $attributes)) {
            $rating->available = 0;
        }
        $rating->save();

        return $rating->fresh();
    }

    public function test_ratings_route_requires_authentication(): void
    {
        $this->getJson('api/users/ratings?page_size=10')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_reply_route_requires_authentication(): void
    {
        $this->postJson('api/trips/1/reply/1', ['comment' => 'Thanks'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_authenticated_user_can_list_ratings_they_received_using_pagination(): void
    {
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $row = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Smooth ride',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($rated, 'api');

        $response = $this->getJson('api/users/ratings?page_size=10');
        $response->assertOk();
        $payload = $response->json();
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
        $ids = array_column($payload['data'], 'id');
        $this->assertContains($row->id, $ids);
    }

    public function test_authenticated_user_can_list_ratings_for_another_user_by_id(): void
    {
        $viewer = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Great',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($viewer, 'api');

        $this->getJson('api/users/'.$rated->id.'/ratings?page_size=10')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_pending_lists_outbound_ratings_awaiting_vote(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $pending = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => null,
            'comment' => '',
            'reply_comment' => '',
            'voted' => false,
            'voted_hash' => 'token-for-mail',
            'rate_at' => null,
            'available' => 0,
        ]);

        $this->actingAs($voter, 'api');

        $response = $this->getJson('api/users/ratings/pending');
        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($pending->id, $ids);
    }

    public function test_pending_as_guest_without_hash_is_rejected(): void
    {
        $this->getJson('api/users/ratings/pending')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Hash not provided']);
    }

    public function test_pending_as_guest_with_hash_returns_matching_rows(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $pending = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => null,
            'comment' => '',
            'reply_comment' => '',
            'voted' => false,
            'voted_hash' => 'guest-hash-abc',
            'rate_at' => null,
            'available' => 0,
        ]);

        $response = $this->getJson('api/users/ratings/pending?hash=guest-hash-abc');
        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($pending->id, $ids);
    }

    public function test_rate_as_authenticated_user_persists_vote_and_returns_ok(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => null,
            'comment' => '',
            'reply_comment' => '',
            'voted' => false,
            'voted_hash' => '',
            'rate_at' => null,
            'available' => 0,
        ]);

        $this->actingAs($voter, 'api');

        $this->postJson("api/trips/{$trip->id}/rate/{$rated->id}", [
            'rating' => 1,
            'comment' => 'All good',
        ])
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertDatabaseHas('rating', [
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'voted' => 1,
        ]);
    }

    public function test_rate_as_guest_with_hash_persists_vote(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => null,
            'comment' => '',
            'reply_comment' => '',
            'voted' => false,
            'voted_hash' => 'mail-link-hash',
            'rate_at' => null,
            'available' => 0,
        ]);

        $this->postJson("api/trips/{$trip->id}/rate/{$rated->id}?hash=mail-link-hash", [
            'rating' => 0,
            'comment' => 'From email',
        ])
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertDatabaseHas('rating', [
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'voted' => 1,
        ]);
    }

    public function test_rate_as_guest_without_hash_returns_error(): void
    {
        $this->postJson('api/trips/1/rate/1', [
            'rating' => 1,
            'comment' => 'x',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Hash not provided']);
    }

    public function test_rate_without_matching_row_returns_error(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $other = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api');

        $this->postJson("api/trips/{$trip->id}/rate/{$other->id}", [
            'rating' => 1,
            'comment' => 'No row',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Could not rate user.']);
    }

    public function test_reply_persists_comment_and_returns_ok(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Thanks',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($rated, 'api');

        $this->postJson("api/trips/{$trip->id}/reply/{$voter->id}", [
            'comment' => 'Glad you enjoyed it',
        ])
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertDatabaseHas('rating', [
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'reply_comment' => 'Glad you enjoyed it',
        ]);
    }

    public function test_reply_second_attempt_fails(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $voter->id]);

        $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Hi',
            'reply_comment' => 'First',
            'reply_comment_created_at' => Carbon::now()->subMinute(),
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($rated, 'api');

        $this->postJson("api/trips/{$trip->id}/reply/{$voter->id}", [
            'comment' => 'Again',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Could not replay user.']);
    }
}
