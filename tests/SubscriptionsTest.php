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
}
