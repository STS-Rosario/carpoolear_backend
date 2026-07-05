<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use STS\Http\Controllers\Api\v1\RatingController;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Logic\RatingManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class RatingApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

    public function test_constructor_registers_expected_logged_middleware_scopes(): void
    {
        $controller = new RatingController(
            Mockery::mock(RatingManager::class),
            Mockery::mock(UsersManager::class)
        );

        $middlewares = $controller->getMiddleware();
        $logged = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });
        $loggedOptional = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged.optional';
        });

        $this->assertNotNull($logged);
        $this->assertNotNull($loggedOptional);

        $loggedOptions = is_array($logged) ? ($logged['options'] ?? []) : ($logged->options ?? []);
        $optionalOptions = is_array($loggedOptional) ? ($loggedOptional['options'] ?? []) : ($loggedOptional->options ?? []);

        $this->assertSame(['rate', 'pendingRate'], $loggedOptions['except'] ?? []);
        $this->assertSame(['rate', 'pendingRate'], $optionalOptions['only'] ?? []);
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

    public function test_authenticated_user_can_list_ratings_when_trip_was_deleted(): void
    {
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $rated->id]);
        $deletedTripId = $trip->id;

        $row = $this->persistRating([
            'trip_id' => $deletedTripId,
            'user_id_from' => $voter->id,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Orphaned trip',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $trip->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->actingAs($rated, 'api');

        $response = $this->getJson('api/users/'.$rated->id.'/ratings?page_size=200');
        $response->assertOk();

        $rating = collect($response->json('data'))->firstWhere('id', $row->id);
        $this->assertNotNull($rating);
        $this->assertDatabaseHas('rating', ['id' => $row->id]);
        $this->assertSame($deletedTripId, $rating['trip']['id']);
        $this->assertSame('Viaje inexistente', $rating['trip']['to_town']);
        $this->assertTrue($rating['trip']['deleted']);
    }

    public function test_authenticated_user_can_list_ratings_when_voter_was_deleted(): void
    {
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $trip = Trip::factory()->create(['user_id' => $rated->id]);
        $deletedVoterId = $voter->id;

        $row = $this->persistRating([
            'trip_id' => $trip->id,
            'user_id_from' => $deletedVoterId,
            'user_id_to' => $rated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'Orphaned voter',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $voter->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->actingAs($rated, 'api');

        $response = $this->getJson('api/users/ratings?page_size=10');
        $response->assertOk();

        $rating = collect($response->json('data'))->firstWhere('id', $row->id);
        $this->assertNotNull($rating);
        $this->assertDatabaseHas('rating', ['id' => $row->id]);
        $this->assertSame($deletedVoterId, $rating['from']['id']);
        $this->assertSame('Usuario ya no existe', $rating['from']['name']);
        $this->assertSame('', $rating['from']['image']);
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

    public function test_ratings_for_own_user_when_route_id_is_numeric_string_skips_users_manager_show(): void
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
            'comment' => 'Same user string id branch',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => '',
            'rate_at' => Carbon::now(),
            'available' => 1,
        ]);

        $this->actingAs($rated, 'api');

        $userLogic = Mockery::mock(UsersManager::class);
        $userLogic->shouldReceive('show')->never();
        $this->instance(UsersManager::class, $userLogic);

        $pathUserId = (string) $rated->id;

        $response = $this->getJson('api/users/'.$pathUserId.'/ratings?page_size=10');
        $response->assertOk();
        $this->assertContains($row->id, array_column($response->json('data'), 'id'));
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

    public function test_pending_omits_repeat_user_when_voter_already_rated_them(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $alreadyRated = User::factory()->create(['active' => true, 'banned' => false]);
        $firstTime = User::factory()->create(['active' => true, 'banned' => false]);

        $priorTrip = Trip::factory()->create(['user_id' => $alreadyRated->id]);
        $this->persistRating([
            'trip_id' => $priorTrip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $alreadyRated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => Rating::STATE_POSITIVO,
            'comment' => 'ok',
            'reply_comment' => '',
            'voted' => true,
            'voted_hash' => 'prior-vote',
            'rate_at' => Carbon::now(),
            'available' => 0,
        ]);

        $repeatTrip = Trip::factory()->create(['user_id' => $alreadyRated->id]);
        $repeatPending = $this->persistRating([
            'trip_id' => $repeatTrip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $alreadyRated->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => null,
            'comment' => '',
            'reply_comment' => '',
            'voted' => false,
            'voted_hash' => 'repeat-pending',
            'rate_at' => null,
            'available' => 0,
        ]);

        $firstTrip = Trip::factory()->create(['user_id' => $firstTime->id]);
        $mandatoryPending = $this->persistRating([
            'trip_id' => $firstTrip->id,
            'user_id_from' => $voter->id,
            'user_id_to' => $firstTime->id,
            'user_to_type' => Passenger::TYPE_PASAJERO,
            'user_to_state' => Passenger::STATE_ACCEPTED,
            'rating' => null,
            'comment' => '',
            'reply_comment' => '',
            'voted' => false,
            'voted_hash' => 'first-pending',
            'rate_at' => null,
            'available' => 0,
        ]);

        $this->actingAs($voter, 'api');

        $response = $this->getJson('api/users/ratings/pending');
        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains($repeatPending->id, $ids);
        $this->assertContains($mandatoryPending->id, $ids);

        Carbon::setTestNow();
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

    public function test_rate_rejects_negative_and_neutral_votes_without_comment(): void
    {
        $voter = User::factory()->create(['active' => true, 'banned' => false]);
        $rated = User::factory()->create(['active' => true, 'banned' => false]);
        $negativeTrip = Trip::factory()->create(['user_id' => $voter->id]);
        $neutralTrip = Trip::factory()->create(['user_id' => $voter->id]);

        $this->persistRating([
            'trip_id' => $negativeTrip->id,
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

        $this->persistRating([
            'trip_id' => $neutralTrip->id,
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

        $this->postJson("api/trips/{$negativeTrip->id}/rate/{$rated->id}", [
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => '',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Could not rate user.'])
            ->assertJsonPath('errors.comment.0', 'The comment field is required for negative and neutral ratings.');

        $this->postJson("api/trips/{$neutralTrip->id}/rate/{$rated->id}", [
            'rating' => Rating::STATE_NEUTRAL,
            'comment' => '   ',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Could not rate user.'])
            ->assertJsonPath('errors.comment.0', 'The comment field is required for negative and neutral ratings.');
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
