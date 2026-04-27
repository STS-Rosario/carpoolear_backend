<?php

namespace Tests\Unit\Repository;

use STS\Models\Device;
use STS\Models\User;
use STS\Repository\DeviceRepository;
use Tests\TestCase;

class DeviceRepositoryTest extends TestCase
{
    private function makeDeviceModel(User $user, array $overrides = []): Device
    {
        $device = new Device;
        $device->forceFill(array_merge([
            'user_id' => $user->id,
            'device_id' => 'dev-'.uniqid('', true),
            'device_type' => 'android',
            'session_id' => 'sess-'.uniqid('', true),
            'app_version' => 1,
            'notifications' => true,
            'language' => 'es',
            'last_activity' => now()->toDateString(),
        ], $overrides));

        return $device;
    }

    public function test_store_persists_device(): void
    {
        $user = User::factory()->create();
        $repo = new DeviceRepository;
        $device = $this->makeDeviceModel($user);

        $this->assertTrue($repo->store($device));
        $this->assertDatabaseHas('users_devices', [
            'user_id' => $user->id,
            'device_id' => $device->device_id,
        ]);
    }

    public function test_update_persists_changes(): void
    {
        $user = User::factory()->create();
        $repo = new DeviceRepository;
        $device = $this->makeDeviceModel($user);
        $repo->store($device);

        $device->app_version = 99;
        $this->assertTrue($repo->update($device));

        $this->assertSame(99, (int) $device->fresh()->app_version);
    }

    public function test_delete_removes_row(): void
    {
        $user = User::factory()->create();
        $repo = new DeviceRepository;
        $device = $this->makeDeviceModel($user);
        $repo->store($device);
        $id = $device->id;

        $repo->delete($device);

        $this->assertNull(Device::query()->find($id));
    }

    public function test_get_devices_returns_only_rows_for_user(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $repo = new DeviceRepository;

        $repo->store($this->makeDeviceModel($u1, ['device_id' => 'a-'.uniqid('', true)]));
        $repo->store($this->makeDeviceModel($u1, ['device_id' => 'b-'.uniqid('', true)]));
        $repo->store($this->makeDeviceModel($u2, ['device_id' => 'c-'.uniqid('', true)]));

        $devices = $repo->getDevices($u1);
        $this->assertCount(2, $devices);
        foreach ($devices as $d) {
            $this->assertSame($u1->id, (int) $d->user_id);
        }
    }

    public function test_get_device_by_returns_first_match(): void
    {
        $user = User::factory()->create();
        $repo = new DeviceRepository;
        $token = 'lookup-'.uniqid('', true);
        $repo->store($this->makeDeviceModel($user, ['session_id' => $token]));

        $found = $repo->getDeviceBy('session_id', $token);
        $this->assertInstanceOf(Device::class, $found);
        $this->assertSame($token, $found->session_id);
    }

    public function test_delete_devices_removes_all_for_user(): void
    {
        $user = User::factory()->create();
        $repo = new DeviceRepository;
        $repo->store($this->makeDeviceModel($user));
        $repo->store($this->makeDeviceModel($user));

        $deleted = $repo->deleteDevices($user);

        $this->assertGreaterThanOrEqual(2, $deleted);
        $this->assertSame(0, Device::query()->where('user_id', $user->id)->count());
    }
}
