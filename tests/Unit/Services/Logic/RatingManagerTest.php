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

    public function test_validator_requires_rating_and_accepts_zero_one_or_two(): void
    {
        $manager = $this->manager();

        $missing = $manager->validator(['comment' => 'x']);
        $this->assertTrue($missing->fails());
        $this->assertTrue($missing->errors()->has('rating'));

        $negativeWithoutComment = $manager->validator(['rating' => 0, 'comment' => null]);
        $this->assertTrue($negativeWithoutComment->fails());
        $this->assertTrue($negativeWithoutComment->errors()->has('comment'));

        $negativeWithWhitespaceComment = $manager->validator(['rating' => 0, 'comment' => '   ']);
        $this->assertTrue($negativeWithWhitespaceComment->fails());
        $this->assertTrue($negativeWithWhitespaceComment->errors()->has('comment'));

        $negativeWithComment = $manager->validator(['rating' => 0, 'comment' => 'Bad trip']);
        $this->assertFalse($negativeWithComment->fails());

        $okOne = $manager->validator(['rating' => 1]);
        $this->assertFalse($okOne->fails());

        $neutralWithoutComment = $manager->validator(['rating' => 2, 'comment' => null]);
        $this->assertTrue($neutralWithoutComment->fails());
        $this->assertTrue($neutralWithoutComment->errors()->has('comment'));

        $okTwo = $manager->validator(['rating' => 2, 'comment' => 'Neutral trip']);
        $this->assertFalse($okTwo->fails());

        $invalid = $manager->validator(['rating' => 3]);
        $this->assertTrue($invalid->fails());
        $this->assertTrue($invalid->errors()->has('rating'));
    }

    public function test_validator_rejects_array_comment_when_rating_is_present(): void
    {
        $manager = $this->manager();
        $v = $manager->validator(['rating' => 1, 'comment' => ['not', 'a', 'string']]);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('comment'));
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

    public function test_get_pending_ratings_by_hash_excludes_repeat_user_when_already_rated_them(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $user = User::factory()->create();
        $alreadyRated = User::factory()->create();
        $firstTime = User::factory()->create();
        $repo = new RatingRepository;

        $priorTrip = Trip::factory()->create(['user_id' => $alreadyRated->id]);
        Rating::factory()->create([
            'trip_id' => $priorTrip->id,
            'user_id_from' => $user->id,
            'user_id_to' => $alreadyRated->id,
            'rating' => Rating::STATE_POSITIVO,
            'voted' => true,
        ]);

        $hash = 'batch-'.uniqid('', true);
        $repeatTrip = Trip::factory()->create(['user_id' => $alreadyRated->id]);
        $repo->create($user->id, $alreadyRated->id, $repeatTrip->id, 0, 0, $hash);

        $firstTrip = Trip::factory()->create(['user_id' => $firstTime->id]);
        $mandatory = $repo->create($user->id, $firstTime->id, $firstTrip->id, 0, 0, $hash);

        $collection = $this->manager()->getPendingRatingsByHash($hash);
        $this->assertCount(1, $collection);
        $this->assertTrue($collection->first()->is($mandatory));

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

    public function test_get_rate_accepts_integer_user_id_input(): void
    {
        Carbon::setTestNow('2026-11-06 10:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, 'gr-int-'.uniqid('', true));

        $rate = $this->manager()->getRate($passenger->id, $driver->id, $trip->id);

        $this->assertInstanceOf(Rating::class, $rate);
        $this->assertSame($passenger->id, (int) $rate->user_id_from);

        Carbon::setTestNow();
    }

    public function test_get_rate_with_hash_returns_pending_rating_within_interval(): void
    {
        Carbon::setTestNow('2026-11-07 10:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $hash = 'gr-hash-'.uniqid('', true);
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, $hash);

        $rate = $this->manager()->getRate($hash, $driver->id, $trip->id);

        $this->assertInstanceOf(Rating::class, $rate);
        $this->assertFalse((bool) $rate->voted);
        $this->assertSame($hash, $rate->voted_hash);

        Carbon::setTestNow();
    }

    public function test_get_rate_with_hash_returns_null_when_no_row_matches(): void
    {
        Carbon::setTestNow('2026-11-07 11:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $hash = 'gr-hash-'.uniqid('', true);
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, $hash);

        $this->assertNull($this->manager()->getRate($hash, $driver->id + 99_999, $trip->id));

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

    public function test_rate_user_persists_neutral_vote(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $repo->create($passenger->id, $driver->id, $trip->id, 0, 0, 'neu-'.uniqid('', true));

        $this->assertTrue($this->manager()->rateUser($passenger, $driver->id, $trip->id, [
            'rating' => Rating::STATE_NEUTRAL,
            'comment' => 'Average experience',
        ]));

        $row = $repo->getRating($passenger->id, $driver->id, $trip->id);
        $this->assertSame(Rating::STATE_NEUTRAL, (int) $row->rating);
        $this->assertSame('Average experience', $row->comment);

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
            'comment' => 'Late and rude',
        ]));

        $row = $repo->getRating($passenger->id, $driver->id, $trip->id);
        $this->assertSame(Rating::STATE_NEGATIVO, (int) $row->rating);

        Carbon::setTestNow();
    }

    public function test_rate_user_rejects_negative_and_neutral_votes_without_comment(): void
    {
        Carbon::setTestNow('2026-11-11 09:30:00');
        $passenger = User::factory()->create();
        $driver = User::factory()->create();
        $negativeTrip = Trip::factory()->create(['user_id' => $driver->id]);
        $neutralTrip = Trip::factory()->create(['user_id' => $driver->id]);
        $repo = new RatingRepository;
        $repo->create($passenger->id, $driver->id, $negativeTrip->id, 0, 0, 'neg-empty-'.uniqid('', true));
        $repo->create($passenger->id, $driver->id, $neutralTrip->id, 0, 0, 'neu-empty-'.uniqid('', true));

        $manager = $this->manager();
        $this->assertNull($manager->rateUser($passenger, $driver->id, $negativeTrip->id, [
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => '',
        ]));
        $this->assertTrue($manager->getErrors()->has('comment'));

        $manager = $this->manager();
        $this->assertNull($manager->rateUser($passenger, $driver->id, $neutralTrip->id, [
            'rating' => Rating::STATE_NEUTRAL,
            'comment' => '   ',
        ]));
        $this->assertTrue($manager->getErrors()->has('comment'));

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

    public function test_reply_rating_sets_error_when_rate_does_not_exist(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $to->id]);

        $manager = $this->manager();
        $result = $manager->replyRating($to, $from->id, $trip->id, 'No row');

        $this->assertNull($result);
        $this->assertSame('user_have_already_replay', $manager->getErrors()['error']);
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

    public function test_active_ratings_processes_only_trips_matching_mail_send_is_passenger_and_date_filters(): void
    {
        Event::fake([PendingRateEvent::class]);
        Carbon::setTestNow('2026-11-20 10:00:00');

        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();

        $eligibleTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-11-01 08:00:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $eligibleTrip->id,
            'user_id' => $passengerUser->id,
        ]);

        $mailAlreadySentTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-11-01 08:00:00',
            'mail_send' => true,
            'is_passenger' => false,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $mailAlreadySentTrip->id,
            'user_id' => $passengerUser->id,
        ]);

        $passengerTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-11-01 08:00:00',
            'mail_send' => false,
            'is_passenger' => true,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $passengerTrip->id,
            'user_id' => $passengerUser->id,
        ]);

        $futureTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-12-01 08:00:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $futureTrip->id,
            'user_id' => $passengerUser->id,
        ]);

        $this->manager()->activeRatings('2026-11-15 12:00:00');

        $this->assertSame(2, Rating::query()->where('trip_id', $eligibleTrip->id)->count());
        $this->assertSame(1, (int) $eligibleTrip->fresh()->mail_send);

        $this->assertSame(0, Rating::query()->where('trip_id', $mailAlreadySentTrip->id)->count());
        $this->assertSame(0, Rating::query()->where('trip_id', $passengerTrip->id)->count());
        $this->assertSame(0, Rating::query()->where('trip_id', $futureTrip->id)->count());

        Event::assertDispatched(PendingRateEvent::class, 2);

        Carbon::setTestNow();
    }

    public function test_active_ratings_deduplicates_same_passenger_and_excludes_canceled_request_type(): void
    {
        Event::fake([PendingRateEvent::class]);
        Carbon::setTestNow('2026-11-21 10:00:00');

        $driver = User::factory()->create();
        $samePassenger = User::factory()->create();
        $excludedPassenger = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-11-01 08:00:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $samePassenger->id,
        ]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $samePassenger->id,
            'request_state' => Passenger::STATE_CANCELED,
            'canceled_state' => Passenger::CANCELED_DRIVER,
        ]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $excludedPassenger->id,
            'request_state' => Passenger::STATE_CANCELED,
            'canceled_state' => Passenger::CANCELED_REQUEST,
        ]);

        $this->manager()->activeRatings('2026-11-15 12:00:00');

        $ratings = Rating::query()->where('trip_id', $trip->id)->get();
        $this->assertCount(2, $ratings);
        $this->assertTrue($ratings->contains(fn ($row) => (int) $row->user_id_from === $driver->id && (int) $row->user_id_to === $samePassenger->id));
        $this->assertTrue($ratings->contains(fn ($row) => (int) $row->user_id_from === $samePassenger->id && (int) $row->user_id_to === $driver->id));
        $this->assertFalse($ratings->contains(fn ($row) => (int) $row->user_id_to === $excludedPassenger->id));
        $this->assertFalse($ratings->contains(fn ($row) => (int) $row->user_id_from === $excludedPassenger->id));

        Event::assertDispatched(PendingRateEvent::class, 2);
        $this->assertSame(1, (int) $trip->fresh()->mail_send);

        Carbon::setTestNow();
    }

    public function test_create_eligible_ratings_creates_pending_rows_after_eighty_percent_of_estimated_time_without_notification(): void
    {
        Event::fake([PendingRateEvent::class]);
        Carbon::setTestNow('2026-06-01 14:00:00');

        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-01 10:00:00',
            'estimated_time' => '04:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $this->manager()->createEligibleRatings();

        $this->assertSame(2, Rating::query()->where('trip_id', $trip->id)->count());
        $this->assertSame(0, (int) $trip->fresh()->mail_send);
        Event::assertNotDispatched(PendingRateEvent::class);

        Carbon::setTestNow();
    }

    public function test_create_eligible_ratings_skips_trips_before_eighty_percent_of_estimated_time(): void
    {
        Event::fake([PendingRateEvent::class]);
        Carbon::setTestNow('2026-06-01 12:00:00');

        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-01 10:00:00',
            'estimated_time' => '04:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $this->manager()->createEligibleRatings();

        $this->assertSame(0, Rating::query()->where('trip_id', $trip->id)->count());
        Event::assertNotDispatched(PendingRateEvent::class);

        Carbon::setTestNow();
    }

    public function test_send_rating_notifications_dispatches_events_after_twenty_four_hours_without_recreating_ratings(): void
    {
        Event::fake([PendingRateEvent::class]);
        Carbon::setTestNow('2026-06-02 11:00:00');

        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => '2026-06-01 10:00:00',
            'estimated_time' => '04:00',
            'mail_send' => false,
            'is_passenger' => false,
        ]);

        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $repo = new RatingRepository;
        $repo->create($driver->id, $passengerUser->id, $trip->id, 0, 0, 'drv-'.uniqid('', true));
        $repo->create($passengerUser->id, $driver->id, $trip->id, 0, 0, 'psg-'.uniqid('', true));

        $this->manager()->sendRatingNotifications('2026-06-01 11:00:00');

        $this->assertSame(2, Rating::query()->where('trip_id', $trip->id)->count());
        $this->assertSame(1, (int) $trip->fresh()->mail_send);
        Event::assertDispatched(PendingRateEvent::class, 2);

        Carbon::setTestNow();
    }
}
