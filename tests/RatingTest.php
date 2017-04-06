<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Entities\Trip;
use STS\Entities\Passenger;
use STS\Entities\Rating;
use STS\User; 

class RatingTest extends TestCase
{
    use DatabaseTransactions;

    protected $ratingManager, $ratingRepository;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
        $this->ratingManager = App::make('\STS\Contracts\Logic\IRateLogic');
        $this->ratingRepository = App::make('\STS\Contracts\Repository\IRatingRepository');
    }

    public function testCreate()
    {
        $driver = factory(User::class)->create();
        $passengers = factory(User::class, 3)->create();
        $trip = factory(Trip::class)->create(['trip_date' => '2017-01-01 08:00:00', 'user_id' => $driver->id ]); 

        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengers[0]->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengers[1]->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengers[2]->id, 'trip_id' => $trip->id]);

        $this->ratingManager->activeRatings("2017-01-01");
        
        $rates = Rating::all();

        $this->assertTrue( $rates->count() == 6);

        $this->ratingManager->activeRatings("2017-01-01");
        $rates = Rating::all(); 
        $this->assertTrue( $rates->count() == 6);

        $this->assertTrue( $this->ratingManager->getRatings($driver)->count() == 0);
    }

    public function testgetRatings()
    {
        $driver = factory(User::class)->create();
        $passengers = factory(User::class, 3)->create();
        $trip = factory(Trip::class)->create(['trip_date' => '2017-01-01 08:00:00', 'user_id' => $driver->id ]); 

        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengers[0]->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengers[1]->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengers[2]->id, 'trip_id' => $trip->id]);
        
        $this->ratingManager->activeRatings("2017-01-01");

        $pending = $this->ratingManager->getPendingRatings($driver);

        $this->assertTrue( $pending->count() == 3);

        $hash = $rates = Rating::where('user_id_from', $driver->id)->first()->voted_hash; 

        $pending = $this->ratingManager->getPendingRatingsByHash($hash);

        $this->assertTrue( $pending->count() == 3);

        $result = $this->ratingManager->rateUser($driver, $passengers[0]->id, $trip->id, ['comment' => "Test comment", 'rating' => 1]);

        $this->assertTrue( $result );

        $result = $this->ratingManager->rateUser($driver, $passengers[0]->id, $trip->id, ['comment' => "Test comment", 'rating' => 1]);
        $this->assertNull( $result );

        $result = $this->ratingManager->replyRating($passengers[0], $driver->id, $trip->id,   "Reply comment" );
        $this->assertTrue( $result );

        $result = $this->ratingManager->replyRating($passengers[0], $driver->id, $trip->id,   "Reply comment" );
        $this->assertNull( $result );

        $this->assertTrue( $this->ratingManager->getRatings($passengers[0])->count() == 1);

    }


 

}

