<?php

namespace Tests\Feature\Http;

use STS\Models\Device;
use STS\Models\User;
use Tests\TestCase;

class DeviceControllerIntegrationTest extends TestCase
{
    private function bearerTokenForUser(User $user): string
    {
        return $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegisterBody(string $deviceIdSuffix = ''): array
    {
        return [
            'device_id' => 'http-dev-'.uniqid($deviceIdSuffix, true),
            'device_type' => 'android',
            'app_version' => 42,
            'notifications' => 1,
        ];
    }

    public function test_device_endpoints_require_authentication(): void
    {
        $checks = [
            ['GET', '/api/devices'],
            ['POST', '/api/devices'],
            ['PUT', '/api/devices/1'],
            ['DELETE', '/api/devices/1'],
            ['POST', '/api/devices/logout'],
        ];

        foreach ($checks as [$method, $uri]) {
            $this->json($method, $uri, $method === 'POST' ? [] : [])
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Unauthorized.');
        }
    }

    public function test_register_returns_device_payload_for_valid_body_and_jwt(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);
        $body = $this->validRegisterBody();

        $this->postJson('/api/devices', $body, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'device_id', 'user_id', 'session_id']])
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.device_id', $body['device_id']);

        $this->assertDatabaseHas('users_devices', [
            'user_id' => $user->id,
            'device_id' => $body['device_id'],
        ]);
    }

    public function test_register_returns_unprocessable_when_validation_fails(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);

        $this->postJson('/api/devices', [
            'device_type' => 'android',
            'app_version' => 1,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bad request exceptions');
    }

    public function test_update_returns_device_payload_when_row_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);
        $deviceId = 'upd-dev-'.uniqid('', true);

        $create = $this->postJson('/api/devices', $this->validRegisterBody($deviceId), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $id = (int) $create->json('data.id');

        $this->putJson('/api/devices/'.$id, [
            'device_id' => $deviceId,
            'device_type' => 'android',
            'app_version' => 99,
            'notifications' => 0,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.app_version', 99);
    }

    public function test_update_returns_unprocessable_when_device_belongs_to_another_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $ownerToken = $this->bearerTokenForUser($owner);
        $intruderToken = $this->bearerTokenForUser($intruder);

        $create = $this->postJson('/api/devices', $this->validRegisterBody('owner'), [
            'Authorization' => 'Bearer '.$ownerToken,
        ])->assertOk();

        $id = (int) $create->json('data.id');

        $this->putJson('/api/devices/'.$id, [
            'device_id' => $create->json('data.device_id'),
            'device_type' => 'android',
            'app_version' => 1,
            'notifications' => 1,
        ], [
            'Authorization' => 'Bearer '.$intruderToken,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bad request exceptions');
    }

    public function test_index_returns_devices_and_active_count(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);

        $this->postJson('/api/devices', $this->validRegisterBody('one'), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->postJson('/api/devices', $this->validRegisterBody('two'), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->getJson('/api/devices', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonStructure(['data', 'count'])
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_delete_returns_ok_string_for_owner_but_does_not_remove_owned_device(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);
        $create = $this->postJson('/api/devices', $this->validRegisterBody(), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $id = (int) $create->json('data.id');

        $response = $this->deleteJson('/api/devices/'.$id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $this->assertSame('"OK"', $response->getContent());
        $this->assertNotNull(Device::query()->find($id));
    }

    public function test_delete_as_non_owner_removes_device_and_returns_ok(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerToken = $this->bearerTokenForUser($owner);
        $otherToken = $this->bearerTokenForUser($other);

        $create = $this->postJson('/api/devices', $this->validRegisterBody('owned'), [
            'Authorization' => 'Bearer '.$ownerToken,
        ])->assertOk();

        $id = (int) $create->json('data.id');

        $this->deleteJson('/api/devices/'.$id, [], [
            'Authorization' => 'Bearer '.$otherToken,
        ])->assertOk();

        $this->assertNull(Device::query()->find($id));
    }

    public function test_logout_returns_success_when_device_matches_current_session(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);

        $this->postJson('/api/devices', $this->validRegisterBody('sess'), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->postJson('/api/devices/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Device logged out successfully');

        $this->assertSame(0, Device::query()->where('user_id', $user->id)->count());
    }

    public function test_logout_returns_unprocessable_when_session_has_no_registered_device(): void
    {
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);

        $this->postJson('/api/devices/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Device not found');
    }
}
