<?php

use Mockery as m;
use STS\Entities\Trip;
use STS\Entities\Rating;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RatingApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $logic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->logic = $this->mock('STS\Contracts\Logic\IRateLogic');
    }

    public function tearDown()
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testGetRatings()
    {
        $driver = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);
        $this->actingAsApiUser($driver);

        $this->logic->shouldReceive('getRatings')->with($driver, [])->once()->andReturn(Rating::paginate(10));

        $response = $this->call('GET', 'api/users/ratings');
        $this->assertTrue($response->status() == 200);
    }

    public function testGetRatingsByID()
    {
        $driver = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);
        $this->actingAsApiUser($driver);

        $this->logic->shouldReceive('getRatings')->with($driver, [])->once()->andReturn(Rating::paginate(10));

        $response = $this->call('GET', 'api/users/'.$driver->id.'/ratings');
        $this->assertTrue($response->status() == 200);
        
    }

    public function testPendings()
    {
        $driver = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);
        $this->actingAsApiUser($driver);

        $this->logic->shouldReceive('getPendingRatings')->with($driver)->once()->andReturn(Rating::all());

        $response = $this->call('GET', 'api/users/ratings/pending');
        $this->assertTrue($response->status() == 200);
    }

    public function testPendingsWithHash()
    {
        $driver = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);
        //$this->actingAsApiUser($driver);

        $this->logic->shouldReceive('getPendingRatings')->with('123456')->once()->andReturn(Rating::all());

        $response = $this->call('GET', 'api/users/ratings/pending?hash=123456');
        $this->assertTrue($response->status() == 200);
    }

    public function testRateUser()
    {
        $driver = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);
        $this->actingAsApiUser($driver);

        $data = [
            'comment' =>'test comment',
            'rating' => 1,
        ];
        $this->logic->shouldReceive('rateUser')->with($driver, 5, 10, $data)->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/10/rate/5', $data);
        $this->assertTrue($response->status() == 200);
    }

    public function testReplayUser()
    {
        $driver = factory(STS\User::class)->create();
        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);
        $this->actingAsApiUser($driver);

        $data = [
            'comment' =>'test comment',
            'rating' => 1,
        ];
        $this->logic->shouldReceive('replyRating')->with($driver, 5, 10, 'test comment')->once()->andReturn(true);

        $response = $this->call('POST', 'api/trips/10/reply/5', $data);
        $this->assertTrue($response->status() == 200);
    }
}
