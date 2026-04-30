<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery as m;
use STS\Http\Controllers\Api\v1\DeviceController;
use STS\Models\User;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;
use Tymon\JWTAuth\Token;

class DeviceApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $deviceLogic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deviceLogic = $this->mock(\STS\Services\Logic\DeviceManager::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function test_constructor_registers_logged_middleware(): void
    {
        $controller = new DeviceController(
            m::mock(UsersManager::class),
            m::mock(DeviceManager::class),
        );

        $logged = collect($controller->getMiddleware())->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);
    }

    public function test_register()
    {
        $u1 = User::factory()->create();
        $this->actingAs($u1, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $payload = ['id' => 1, 'device_id' => 'device-a'];
        $this->deviceLogic->shouldReceive('register')
            ->once()
            ->with($u1, m::on(fn ($data) => isset($data['session_id']) && $data['session_id'] === 'a.b.c'))
            ->andReturn($payload);

        $this->postJson('api/devices', [])
            ->assertOk()
            ->assertExactJson(['data' => $payload]);
    }

    public function test_update()
    {
        $u1 = User::factory()->create();
        $this->actingAs($u1, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $payload = ['id' => 1, 'app_version' => 2];
        $this->deviceLogic->shouldReceive('update')
            ->once()
            ->with($u1, '1', m::on(fn ($data) => isset($data['session_id']) && $data['session_id'] === 'a.b.c'))
            ->andReturn($payload);

        $this->putJson('api/devices/1', [])
            ->assertOk()
            ->assertExactJson(['data' => $payload]);
    }

    public function test_register_returns_unprocessable_when_manager_returns_null(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('x.y.z'));
        $this->deviceLogic->shouldReceive('register')->once()->andReturn(null);
        $this->deviceLogic->shouldReceive('getErrors')->once()->andReturn(['field' => ['invalid']]);

        $this->postJson('api/devices', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bad request exceptions');
    }

    public function test_delete()
    {
        $u1 = User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->deviceLogic->shouldReceive('delete')->once()->with(1, $u1);

        $response = $this->deleteJson('api/devices/1');
        $response->assertOk();
        $this->assertSame('OK', $response->json());
    }

    public function test_logout_returns_success_payload_when_session_matches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('sess.token.here'));
        $this->deviceLogic->shouldReceive('logoutDevice')
            ->once()
            ->with('sess.token.here', $user)
            ->andReturn(true);

        $this->postJson('api/devices/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Device logged out successfully']);
    }

    public function test_logout_returns_unprocessable_when_device_not_found(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        $this->deviceLogic->shouldReceive('logoutDevice')->once()->with('a.b.c', $user)->andReturn(false);

        $this->postJson('api/devices/logout')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Device not found');
    }

    public function test_index()
    {
        $u1 = User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->deviceLogic->shouldReceive('getDevices')->once()->andReturn([]);
        $this->deviceLogic->shouldReceive('getActiveDevicesCount')->once()->andReturn(0);

        $this->getJson('api/devices')
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'count' => 0,
            ]);
    }
}
