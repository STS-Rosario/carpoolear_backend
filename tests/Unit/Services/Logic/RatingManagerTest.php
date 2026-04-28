<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use STS\Events\Rating\PendingRate as PendingRateEvent;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\RatingRepository;
use STS\Services\Logic\RatingManager;
use Tests\TestCase;

class RatingManagerTest extends TestCase
{
    private function manager(): RatingManager
    {
        return $this->app->make(RatingManager::class);
    }

    public function test_validator_requires_rating_and_accepts_zero_or_one(): void
    {
        $manager = $this->manager();

        $missing = $manager->validator(['comment' => 'x']);
        $this->assertTrue($missing->fails());
        $this->assertTrue($missing->errors()->has('rating'));

        $okZero = $manager->validator(['rating' => 0, 'comment' => null]);
        $this->assertFalse($okZero->fails());

        $okOne = $manager->validator(['rating' => 1]);
        $this->assertFalse($okOne->fails());
    }

    public function test_get_ratings_delegates_to_repository(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $passenger->id,
            'user_id_to' => $driver->id,
            'rating' => Rating::STATE_POSITIVO,
            'voted' => true,
            'available' => true,
        ]);

        $list = $this->manager()->getRatings($driver, []);
        $this->assertCount(1, $list);
        $this->assertSame($driver->id, (int) $list->first()->user_id_to);
    }

    public function test_get_pending_ratings_delegates_to_repository(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);
        $repo = new RatingRepository;
        $repo->create($user->id, $other->id, $trip->id, 0, 0, 'pend-'.uniqid('', true));

        $pending = $this->manager()->getPendingRatings($user);
        $this->assertCount(1, $pending);
        $this->assertFalse((bool) $pending->first()->voted);

        Carbon::setTestNow();
    }

    public function test_get_pending_ratings_by_hash_returns_only_pending_in_window(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);
        $repo = new RatingRepository;

        $hash = 'batch-'.uniqid('', true);
        $open = $repo->create($user->id, $other->id, $trip->id, 0, 0, $hash);
        $stale = $repo->create($user->id, $other->id, $trip->id, 0, 0, $hash);
        $stale->forceFill(['created_at' => '2026-08-01 00:00:00'])->saveQuietly();

        $collection = $this->manager()->getPendingRatingsByHash($hash);
        $this->assertCount(1, $collection);
        $this->assertTrue($collection->first()->is($open));

        Carbon::setTestNow();
    }

    public function test_get_rate_returns_pending_rating_for_user_within_interval(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, 'gr-'.uniqid('', true));

        $rate = $this->manager()->getRate($passenger, $driver->id, $trip->id);
        $this->assertInstanceOf(Rating::class, $rate);
        $this->assertFalse((bool) $rate->voted);

        Carbon::setTestNow();
    }

    public function test_get_rate_returns_null_when_outside_rating_interval(): void
    {
        Carbon::setTestNow('2026-12-01 12:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $pending = $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, 'old-'.uniqid('', true));
        $pending->forceFill(['created_at' => '2026-10-01 00:00:00'])->saveQuietly();

        $this->assertNull($this->manager()->getRate($passenger, $driver->id, $trip->id));

        Carbon::setTestNow();
    }

    public function test_rate_user_validation_failure_sets_errors(): void
    {
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $manager = $this->manager();
        $result = $manager->rateUser($passenger, $driver->id, $trip->id, []);

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('rating'));
    }

    public function test_rate_user_persists_positive_vote_and_marks_voted(): void
    {
        Carbon::setTestNow('2026-11-10 15:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, 'vote-'.uniqid('', true));

        $manager = $this->manager();
        $this->assertTrue($manager->rateUser($passenger, $driver->id, $trip->id, [
            'rating' => 1,
            'comment' => 'Great trip',
        ]));

        $row = $repo->getRating($passenger->id, $driver->id, $trip->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->voted);
        $this->assertSame(Rating::STATE_POSITIVO, (int) $row->rating);
        $this->assertSame('Great trip', $row->comment);
        $this->assertSame('', $row->voted_hash);
        $this->assertSame('2026-11-10 15:00:00', Carbon::parse($row->rate_at)->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_rate_user_persists_negative_vote(): void
    {
        Carbon::setTestNow('2026-11-11 09:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, 'neg-'.uniqid('', true));

        $this->assertTrue($this->manager()->rateUser($passenger, $driver->id, $trip->id, [
            'rating' => 0,
            'comment' => null,
        ]));

        $row = $repo->getRating($passenger->id, $driver->id, $trip->id);
        $this->assertSame(Rating::STATE_NEGATIVO, (int) $row->rating);

        Carbon::setTestNow();
    }

    public function test_rate_user_when_already_voted_sets_error(): void
    {
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $passenger->id,
            'user_id_to' => $driver->id,
            'rating' => Rating::STATE_POSITIVO,
            'voted' => true,
            'voted_hash' => '',
            'available' => true,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->rateUser($passenger, $driver->id, $trip->id, [
            'rating' => 1,
            'comment' => 'x',
        ]));

        $errors = $manager->getErrors();
        $this->assertIsArray($errors);
        $this->assertSame('user_have_already_voted', $errors['error']);
    }

    public function test_reply_rating_persists_comment_once(): void
    {
        Carbon::setTestNow('2026-11-12 14:00:00');
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $bob->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $alice->id,
            'user_id_to' => $bob->id,
            'rating' => Rating::STATE_POSITIVO,
            'voted' => true,
            'reply_comment' => '',
            'reply_comment_created_at' => null,
        ]);

        $manager = $this->manager();
        $this->assertTrue($manager->replyRating($bob, $alice->id, $trip->id, 'Thanks!'));

        $row = (new RatingRepository)->getRating($alice->id, $bob->id, $trip->id);
        $this->assertSame('Thanks!', $row->reply_comment);
        $this->assertNotNull($row->reply_comment_created_at);

        $this->assertNull($manager->replyRating($bob, $alice->id, $trip->id, 'Again'));
        $this->assertSame('user_have_already_replay', $manager->getErrors()['error']);

        Carbon::setTestNow();
    }

    public function test_active_ratings_creates_pending_rows_dispatches_events_and_marks_mail_send(): void
    {
        Event::fake([PendingRateEvent::class]);
        Carbon::setTestNow('2026-11-20 10:00:00');

        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-11-01 08:00:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $this->manager()->activeRatings('2026-11-15 12:00:00');

        $this->assertSame(1, (int) $trip->fresh()->mail_send);
        $this->assertSame(2, Rating::query()->where('trip_id', $trip->id)->count());

        Event::assertDispatched(PendingRateEvent::class, 2);

        Carbon::setTestNow();
    }
}
