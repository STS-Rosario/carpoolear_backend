<?php

use STS\User;
use STS\Entities\Trip;
use STS\Entities\Subscription;
use STS\Transformers\RatingTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SubscriptionsTest extends TestCase
{
    use DatabaseTransactions;

    protected $subscriptionsManager;
    protected $subscriptionsRepository;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
        $this->subscriptionsManager = App::make('\STS\Contracts\Logic\Subscription');
        $this->subscriptionsRepository = App::make('\STS\Contracts\Repository\Subscription');
    }

    public function testCreateSubscription()
    {
        $user = factory(STS\User::class)->create();
        $data = [
            'trip_date'       => Carbon\Carbon::now()->addHour()
        ];

        $model = $this->subscriptionsManager->create($user, $data);

        $this->assertTrue($model != null);
        $this->assertTrue($model->user_id === $user->id);

    }

    public function testUpdateSubscription()
    {
        $user = factory(STS\User::class)->create();
        $model  = factory(STS\Entities\Subscription::class)->create(['user_id' => $user->id]);
        $data = [
            'trip_date'       => Carbon\Carbon::now()->addHour()
        ];

        $updated_model = $this->subscriptionsManager->update($user, $model->id, $data);
        $this->assertTrue($model->trip_date != $updated_model->trip_date);
    }

    public function testShowSubscription()
    {
        $user = factory(STS\User::class)->create();
        $model  = factory(STS\Entities\Subscription::class)->create(['user_id' => $user->id]);

        $showed_model = $this->subscriptionsManager->show($user, $model->id);
        $this->assertTrue($model->trip_date == $showed_model->trip_date);
    }

    public function testDeleteCar()
    {
        $user = factory(STS\User::class)->create();
        $model  = factory(STS\Entities\Subscription::class)->create(['user_id' => $user->id]);

        $result = $this->subscriptionsManager->delete($user, $model->id);
        $this->assertTrue($result);
    }

    public function testIndexCar()
    {
        $user = factory(STS\User::class)->create();
        $model  = factory(STS\Entities\Subscription::class)->create(['user_id' => $user->id]);
        $model  = factory(STS\Entities\Subscription::class)->create(['user_id' => $user->id]);

        $result = $this->subscriptionsManager->index($user);
        $this->assertTrue($result->count() == 2);
    }

    public function testMatcher () {

        $user1 = factory(STS\User::class)->create();
        $user2 = factory(STS\User::class)->create();

        $model  = factory(STS\Entities\Subscription::class)->create(['user_id' => $user1->id]);
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user2->id]);

        $trip->points()->save(factory(STS\Entities\TripPoint::class, 'rosario')->make());
        $trip->points()->save(factory(STS\Entities\TripPoint::class, 'mendoza')->make());



        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertTrue($ss->count() == 1);
    }

    public function testMatcher2() {

        $user1 = factory(STS\User::class)->create();
        $user2 = factory(STS\User::class)->create();

        $model  = factory(STS\Entities\Subscription::class)->create([
            'user_id' => $user1->id,
            'to_address'      => 'Mendoza, Mendoza, Argentina',
            'to_json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'to_lat'          => -32.897273,
            'to_lng'          => -68.834067,
            'to_sin_lat'          => sin(deg2rad( -32.897273)),
            'to_sin_lng'          => sin(deg2rad(-68.834067)),
            'to_cos_lat'          => cos(deg2rad( -32.897273)),
            'to_cos_lng'          => cos(deg2rad(-68.834067)),
            'to_radio'          => 10000,
        ]);
        $trip = factory(STS\Entities\Trip::class)->create([
            'friendship_type_id' => 2,
            'user_id' => $user2->id
        ]);

        $trip->points()->save(factory(STS\Entities\TripPoint::class, 'rosario')->make());
        $trip->points()->save(factory(STS\Entities\TripPoint::class, 'mendoza')->make());
 
        $ss = $this->subscriptionsRepository->search($user2, $trip); 
        $this->assertTrue($ss->count() == 1);
    }

    public function testMatcher3() {

        $user1 = factory(STS\User::class)->create();
        $user2 = factory(STS\User::class)->create();

        $model  = factory(STS\Entities\Subscription::class)->create([
            'user_id' => $user1->id,
            'to_address'      => 'Mendoza, Mendoza, Argentina',
            'to_json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'to_lat'          => -32.897273,
            'to_lng'          => -68.834067,
            'to_sin_lat'          => sin(deg2rad( -32.897273)),
            'to_sin_lng'          => sin(deg2rad(-68.834067)),
            'to_cos_lat'          => cos(deg2rad( -32.897273)),
            'to_cos_lng'          => cos(deg2rad(-68.834067)),
            'to_radio'          => 10000,

            'from_address'      => 'Cordoba, Cordoba, Argentina',
            'from_json_address' => ['ciudad' => 'Cordoba', 'provincia' => 'Cordoba'],
            'from_lat'          => -31.421045,
            'from_lng'          => -64.190543,
            'from_sin_lat'          => sin(deg2rad(-31.421045)),
            'from_sin_lng'          => sin(deg2rad(-64.190543)),
            'from_cos_lat'          => cos(deg2rad(-31.421045)),
            'from_cos_lng'          => cos(deg2rad(-64.190543)),
            'from_radio'          => 1000,
        ]);
        $trip = factory(STS\Entities\Trip::class)->create([
            'user_id' => $user2->id
        ]);

        $trip->points()->save(factory(STS\Entities\TripPoint::class, 'rosario')->make());
        $trip->points()->save(factory(STS\Entities\TripPoint::class, 'mendoza')->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertTrue($ss->count() == 0);
    }
}
