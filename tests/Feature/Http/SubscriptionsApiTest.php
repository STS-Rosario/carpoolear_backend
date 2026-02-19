<?php

namespace Tests\Feature\Http;

use Tests\TestCase;
use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SubscriptionsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $subscriptionsLogic;

    public function setUp(): void
    {
        parent::setUp();
        $this->subscriptionsLogic = $this->mock(\STS\Services\Logic\SubscriptionsManager::class);
    }

    public function tearDown(): void
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testCreate()
    {
        $u1 = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->subscriptionsLogic->shouldReceive('create')->once()->andReturn($model);

        $response = $this->call('POST', 'api/subscriptions/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->subscriptionsLogic->shouldReceive('update')->once()->andReturn($model);

        $response = $this->call('PUT', 'api/subscriptions/'.$model->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->subscriptionsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/subscriptions/'.$model->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testShow()
    {
        $u1 = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->subscriptionsLogic->shouldReceive('show')->once()->andReturn($model);

        $response = $this->call('GET', 'api/subscriptions/'.$model->id);
        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($model->id == $response->data->id);
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $model = \STS\Models\Subscription::factory()->create(['user_id' => $u1->id]);
        $this->actingAs($u1, 'api');

        $this->subscriptionsLogic->shouldReceive('index')->once()->andReturn([$model]);

        $response = $this->call('GET', 'api/subscriptions/');
        // $response = $this->parseJson($response);
        // console_log($response);
        $this->assertTrue($response->status() == 200);
    }
}
