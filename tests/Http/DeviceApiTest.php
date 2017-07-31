<?php

use Mockery as m;
use Tymon\JWTAuth\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DeviceApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $deviceLogic;

    public function __construct()
    {
    }

    public function setUp()
    {
        parent::setUp();
        $this->deviceLogic = $this->mock('STS\Contracts\Logic\Devices');
    }

    public function tearDown()
    {
        m::close();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testRegister()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $this->deviceLogic->shouldReceive('register')->once()->andReturn(['id' => 1]);

        $response = $this->call('POST', 'api/devices/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $this->deviceLogic->shouldReceive('update')->once()->andReturn(['id' => 1]);

        $response = $this->call('PUT', 'api/devices/1');
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        $this->deviceLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/devices/1');
        $this->assertTrue($response->status() == 200);
    }

    public function testIndex()
    {
        $u1 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        $this->deviceLogic->shouldReceive('getDevices')->with($u1)->once()->andReturn([]);

        $response = $this->call('GET', 'api/devices');
        $this->assertTrue($response->status() == 200);
    }
}
