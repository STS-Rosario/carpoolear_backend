<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\RatingRepository;
use STS\Services\Logic\RatingManager;
use STS\Transformers\RatingTransformer;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use DatabaseTransactions;

    private RatingManager $ratingManager;

    private RatingRepository $ratingRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ratingManager = $this->app->make(RatingManager::class);
        $this->ratingRepository = $this->app->make(RatingRepository::class);
        Carbon::setTestNow('2028-01-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tripWithAcceptedPassengers(int $count = 3): array
    {
        $driver = User::factory()->create();
        $passengers = User::factory()->count($count)->create();
        $trip = Trip::factory()->create([
            'trip_date' => Carbon::now()->subDays(2),
            'user_id' => $driver->id,
        ]);

        foreach ($passengers as $passenger) {
            Passenger::factory()->aceptado()->create([
                'user_id' => $passenger->id,
                'trip_id' => $trip->id,
            ]);
        }

        return [$driver, $passengers, $trip];
    }

    public function test_active_ratings_creates_pending_rows_once_per_pair(): void
    {
        [$driver, $passengers, $trip] = $this->tripWithAcceptedPassengers(3);

        $this->ratingManager->activeRatings(Carbon::now()->toDateTimeString());
        $this->assertSame(6, Rating::count(), '3 passengers + driver should produce 6 pending ratings');

        $this->ratingManager->activeRatings(Carbon::now()->toDateTimeString());
        $this->assertSame(6, Rating::count(), 'running twice should not duplicate pending ratings');
        $this->assertCount(0, $this->ratingManager->getRatings($driver));
        $this->assertCount(0, $this->ratingManager->getRatings($passengers->first()));
        $this->assertSame(1, (int) $trip->fresh()->mail_send);
    }

    public function test_rate_user_and_reply_flow_is_idempotent(): void
    {
        [$driver, $passengers, $trip] = $this->tripWithAcceptedPassengers(3);
        $this->ratingManager->activeRatings(Carbon::now()->toDateTimeString());
        $targetPassenger = $passengers[0];
        $pending = $this->ratingManager->getPendingRatings($driver);
        $this->assertCount(3, $pending);

        $hash = Rating::where('user_id_from', $driver->id)->first()->voted_hash;
        $this->assertNotSame('', $hash);
        $this->assertCount(3, $this->ratingManager->getPendingRatingsByHash($hash));

        $result = $this->ratingManager->rateUser($driver, $targetPassenger->id, $trip->id, [
            'comment' => 'Test comment',
            'rating' => 1,
        ]);
        $this->assertTrue($result);
        $row = $this->ratingRepository->getRating($driver->id, $targetPassenger->id, $trip->id);
        $this->assertTrue((bool) $row->voted);
        $this->assertSame(Rating::STATE_POSITIVO, (int) $row->rating);
        $this->assertSame('', $row->voted_hash);

        $this->assertNull($this->ratingManager->rateUser($driver, $targetPassenger->id, $trip->id, [
            'comment' => 'Second vote',
            'rating' => 1,
        ]));
        $this->assertSame('user_have_already_voted', $this->ratingManager->getErrors()['error']);

        $this->assertTrue($this->ratingManager->replyRating($targetPassenger, $driver->id, $trip->id, 'Reply comment'));
        $this->assertNull($this->ratingManager->replyRating($targetPassenger, $driver->id, $trip->id, 'Again'));
        $this->assertSame('user_have_already_replay', $this->ratingManager->getErrors()['error']);
    }

    public function test_delete_trip_listener_creates_driver_ratings_and_transformer_keeps_trip_payload(): void
    {
        [$driver, $passengers, $trip] = $this->tripWithAcceptedPassengers(2);
        $event = new \STS\Events\Trip\Delete($trip);
        $listener = new \STS\Listeners\Ratings\CreateRatingDeleteTrip($this->ratingRepository);
        $listener->handle($event);
        $this->assertSame(2, Rating::where('trip_id', $trip->id)->count());

        $passengerIds = $passengers->pluck('id')->all();
        foreach (Rating::where('trip_id', $trip->id)->get() as $rate) {
            $this->assertContains((int) $rate->user_id_from, $passengerIds);
            $this->assertSame($driver->id, (int) $rate->user_id_to);
            $this->assertFalse((bool) $rate->voted);
            $this->assertNotEmpty($rate->voted_hash);
        }

        $trip->delete();
        $rate = Rating::where('trip_id', $trip->id)->first();
        $fractal = (new RatingTransformer($rate->from))->transform($rate);
        $this->assertIsArray($fractal['trip']);
        $this->assertSame($trip->id, $fractal['trip']['id']);
    }
}
