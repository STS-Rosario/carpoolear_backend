<?php

namespace Tests;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use STS\Models\Rating;
use STS\Models\Passenger;
use STS\Transformers\RatingTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RatingTest extends TestCase
{
    use DatabaseTransactions;

    protected $ratingManager;

    protected $ratingRepository;

    public function setUp(): void
    {
        parent::setUp();
        start_log_query();
        $this->ratingManager = \App::make(\STS\Services\Logic\RatingManager::class);
        $this->ratingRepository = \App::make(\STS\Repository\RatingRepository::class);
    }

    public function testCreate()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengers = \STS\Models\User::factory()->count(3)->create();
        $trip = \STS\Models\Trip::factory()->create(['trip_date' => '2017-01-01 08:00:00', 'user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengers[0]->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengers[1]->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengers[2]->id, 'trip_id' => $trip->id]);

        $this->ratingManager->activeRatings('2017-01-01 10:00:00');

        $rates = Rating::all();

        $this->assertTrue($rates->count() == 6);

        $this->ratingManager->activeRatings('2017-01-01');
        $rates = Rating::all();
        $this->assertTrue($rates->count() == 6);

        $this->assertTrue($this->ratingManager->getRatings($driver)->count() == 0);
    }

    public function testgetRatings()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengers = \STS\Models\User::factory()->count(3)->create();
        $trip = \STS\Models\Trip::factory()->create(['trip_date' => '2017-01-01 08:00:00', 'user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengers[0]->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengers[1]->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengers[2]->id, 'trip_id' => $trip->id]);

        $this->ratingManager->activeRatings('2017-01-01 10:00:00');

        $pending = $this->ratingManager->getPendingRatings($driver);

        $this->assertTrue($pending->count() == 3);

        $hash = $rates = Rating::where('user_id_from', $driver->id)->first()->voted_hash;

        $pending = $this->ratingManager->getPendingRatingsByHash($hash);

        $this->assertTrue($pending->count() == 3);

        $trip->delete();
        $result = $this->ratingManager->rateUser($driver, $passengers[0]->id, $trip->id, ['comment' => 'Test comment', 'rating' => 1]);

        $this->assertTrue($result);

        $result = $this->ratingManager->rateUser($driver, $passengers[0]->id, $trip->id, ['comment' => 'Test comment', 'rating' => 1]);
        $this->assertNull($result);

        $result = $this->ratingManager->replyRating($passengers[0], $driver->id, $trip->id, 'Reply comment');
        $this->assertTrue($result);

        $result = $this->ratingManager->replyRating($passengers[0], $driver->id, $trip->id, 'Reply comment');
        $this->assertNull($result);

        // $this->assertTrue($this->ratingManager->getRatings($passengers[0])->count() == 1);
    }

    public function testDeleteListeners()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $event = new \STS\Events\Trip\Delete($trip);

        $listener = new \STS\Listeners\Ratings\CreateRatingDeleteTrip($this->ratingRepository);

        $listener->handle($event);

        $this->assertNotNull(\STS\Services\Notifications\Models\DatabaseNotification::all()->count() == 2);

        $trip->delete();

        $rate = Rating::first();

        $fratal = (new RatingTransformer($rate->from))->transform($rate);

        $this->assertNotNull($fratal['trip']);
    }
}
