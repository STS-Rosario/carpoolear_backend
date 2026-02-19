<?php

namespace Tests\Unit;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\Trip;
use STS\Models\Subscription;
use STS\Transformers\RatingTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SubscriptionsTest extends TestCase
{
    use DatabaseTransactions;

    protected $subscriptionsManager;

    protected $subscriptionsRepository;

    public function setUp(): void
    {
        parent::setUp();
        start_log_query();
        $this->subscriptionsManager = \App::make(\STS\Services\Logic\SubscriptionsManager::class);
        $this->subscriptionsRepository = \App::make(\STS\Repository\SubscriptionsRepository::class);
    }

    public function testCreateSubscription()
    {
        $user = \STS\Models\User::factory()->create();
        $data = [
            'trip_date'       => \Carbon\Carbon::now()->addHour(),
        ];

        $model = $this->subscriptionsManager->create($user, $data);

        $this->assertTrue($model != null);
        $this->assertTrue($model->user_id === $user->id);
    }

    public function testUpdateSubscription()
    {
        $user = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $user->id]);
        $data = [
            'trip_date'       => \Carbon\Carbon::now()->addHour(),
        ];

        $updated_model = $this->subscriptionsManager->update($user, $model->id, $data);
        $this->assertTrue($model->trip_date != $updated_model->trip_date);
    }

    public function testShowSubscription()
    {
        $user = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $user->id]);

        $showed_model = $this->subscriptionsManager->show($user, $model->id);
        $this->assertTrue($model->trip_date == $showed_model->trip_date);
    }

    public function testDeleteCar()
    {
        $user = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $user->id]);

        $result = $this->subscriptionsManager->delete($user, $model->id);
        $this->assertTrue($result);
    }

    public function testIndexCar()
    {
        $user = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $user->id]);
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $user->id]);

        $result = $this->subscriptionsManager->index($user);
        $this->assertTrue($result->count() == 2);
    }

    public function testMatcher()
    {
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();

        $model = \STS\Models\Subscription::factory()->create(['user_id' => $user1->id, 'trip_date' => null]);
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $user2->id]);

        $trip->points()->save(\STS\Models\TripPoint::factory()->rosario()->make());
        $trip->points()->save(\STS\Models\TripPoint::factory()->mendoza()->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertTrue($ss->count() == 1);
    }

    public function testMatcher2()
    {
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();

        $model = \STS\Models\Subscription::factory()->create([
            'user_id' => $user1->id,
            'trip_date' => null,
            'to_address'      => 'Mendoza, Mendoza, Argentina',
            'to_json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'to_lat'          => -32.897273,
            'to_lng'          => -68.834067,
            'to_sin_lat'          => sin(deg2rad(-32.897273)),
            'to_sin_lng'          => sin(deg2rad(-68.834067)),
            'to_cos_lat'          => cos(deg2rad(-32.897273)),
            'to_cos_lng'          => cos(deg2rad(-68.834067)),
            'to_radio'          => 10000,
        ]);
        $trip = \STS\Models\Trip::factory()->create([
            'friendship_type_id' => 2,
            'user_id' => $user2->id,
        ]);

        $trip->points()->save(\STS\Models\TripPoint::factory()->rosario()->make());
        $trip->points()->save(\STS\Models\TripPoint::factory()->mendoza()->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertTrue($ss->count() == 1);
    }

    public function testMatcher3()
    {
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();

        $model = \STS\Models\Subscription::factory()->create([
            'user_id' => $user1->id,
            'trip_date' => null,
            'to_address'      => 'Mendoza, Mendoza, Argentina',
            'to_json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'to_lat'          => -32.897273,
            'to_lng'          => -68.834067,
            'to_sin_lat'          => sin(deg2rad(-32.897273)),
            'to_sin_lng'          => sin(deg2rad(-68.834067)),
            'to_cos_lat'          => cos(deg2rad(-32.897273)),
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
        $trip = \STS\Models\Trip::factory()->create([
            'user_id' => $user2->id,
        ]);

        $trip->points()->save(\STS\Models\TripPoint::factory()->rosario()->make());
        $trip->points()->save(\STS\Models\TripPoint::factory()->mendoza()->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertTrue($ss->count() == 0);
    }
}
