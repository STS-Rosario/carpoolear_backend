<?php

namespace Tests\Feature\Http;

use Tests\TestCase;
use Mockery as m;
use Tymon\JWTAuth\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DeviceApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $deviceLogic;

    public function setUp(): void
    {
        parent::setUp();
        $this->deviceLogic = $this->mock(\STS\Services\Logic\DeviceManager::class);
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testRegister()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $this->deviceLogic->shouldReceive('register')->once()->andReturn(['id' => 1]);

        $response = $this->call('POST', 'api/devices/');
        $this->assertTrue($response->status() == 200);
    }

    public function testUpdate()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $this->deviceLogic->shouldReceive('update')->once()->andReturn(['id' => 1]);

        $response = $this->call('PUT', 'api/devices/1');
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->deviceLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('DELETE', 'api/devices/1');
        $this->assertTrue($response->status() == 200);
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->deviceLogic->shouldReceive('getDevices')->once()->andReturn([]);
        $this->deviceLogic->shouldReceive('getActiveDevicesCount')->once()->andReturn(0);

        $response = $this->call('GET', 'api/devices');
        $this->assertTrue($response->status() == 200);
    }
}
