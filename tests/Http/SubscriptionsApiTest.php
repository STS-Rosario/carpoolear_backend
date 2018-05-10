<?php

use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SubscriptionApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $subscriptionsLogic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->subscriptionsLogic = $this->mock('STS\Contracts\Logic\Subscription');
    }

    public function tearDown()
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testCreate()
    {
        $u1 = factory(STS\User::class)->create();
        $model = factory(STS\Entities\Subscription::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->subscriptionsLogic->shouldReceive('create')->once()->andReturn($model);

        $response = $this->call('POST', 'api/subscriptions/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = factory(STS\User::class)->create();
        $model = factory(STS\Entities\Subscription::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->subscriptionsLogic->shouldReceive('update')->once()->andReturn($model);

        $response = $this->call('PUT', 'api/subscriptions/'.$model->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = factory(STS\User::class)->create();
        $model = factory(STS\Entities\Subscription::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->subscriptionsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/subscriptions/'.$model->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testShow()
    {
        $u1 = factory(STS\User::class)->create();
        $model = factory(STS\Entities\Subscription::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->subscriptionsLogic->shouldReceive('show')->once()->andReturn($model);

        $response = $this->call('GET', 'api/subscriptions/'.$model->id);
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($model->id == $response->data->id);
    }

    public function testIndex()
    {
        $u1 = factory(STS\User::class)->create();
        $model = factory(STS\Entities\Subscription::class)->create(['user_id' => $u1->id]);
        $this->actingAsApiUser($u1);

        $this->subscriptionsLogic->shouldReceive('index')->once()->andReturn([$model]);

        $response = $this->call('GET', 'api/subscriptions/');
        // $response = $this->parseJson($response);
        // console_log($response);
        $this->assertTrue($response->status() == 200);
    }
}
