<?php

use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class NotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $logic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->logic = $this->mock('STS\Contracts\Logic\INotification');
    }

    public function tearDown()
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testIndex()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        $this->logic->shouldReceive('getNotifications')->once()->andReturn([]);

        $response = $this->call('GET', 'api/notifications/');
        $this->assertTrue($response->status() == 200);
    }

    public function testCount()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        $this->logic->shouldReceive('getUnreadCount')->once()->andReturn(5);

        $response = $this->call('GET', 'api/notifications/count');
        $this->assertTrue($response->status() == 200);
        $this->assertTrue($this->parseJson($response)->data == 5);
    }

    public function testDelete()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        $this->logic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/notifications/1');
        $this->assertTrue($response->status() == 200);
    }
}
