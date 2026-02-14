<?php

namespace Tests\Http;

use Tests\TestCase;
use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class NotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $logic;

    public function setUp(): void
    {
        parent::setUp();
        $this->logic = $this->mock(\STS\Services\Logic\NotificationManager::class);
    }

    public function tearDown(): void
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('getNotifications')->once()->andReturn([]);

        $response = $this->call('GET', 'api/notifications/');
        $this->assertTrue($response->status() == 200);
    }

    public function testCount()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('getUnreadCount')->once()->andReturn(5);

        $response = $this->call('GET', 'api/notifications/count');
        $this->assertTrue($response->status() == 200);
        $this->assertTrue($this->parseJson($response)->data == 5);
    }

    public function testDelete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->logic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/notifications/1');
        $this->assertTrue($response->status() == 200);
    }
}
