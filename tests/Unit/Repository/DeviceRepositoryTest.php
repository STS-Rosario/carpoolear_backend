<?php

namespace Tests\Unit\Repository;

use Mockery;
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

    public function test_get_devices_returns_empty_when_user_has_no_devices(): void
    {
        // Mutation intent: preserve `Device::where('user_id', …)->get()` empty collection (~25–28).
        $user = User::factory()->create();
        $devices = (new DeviceRepository)->getDevices($user);

        $this->assertCount(0, $devices);
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

    public function test_get_device_by_returns_null_when_no_row_matches(): void
    {
        // Mutation intent: preserve `Device::where($key, $value)->first()` empty result (~30–33).
        $this->assertNull((new DeviceRepository)->getDeviceBy('session_id', 'missing-sess-'.uniqid('', true)));
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

    public function test_delete_devices_returns_zero_when_user_has_no_devices(): void
    {
        // Mutation intent: preserve `Device::where('user_id', …)->delete()` affect-rows (~35–37).
        $user = User::factory()->create();

        $deleted = (new DeviceRepository)->deleteDevices($user);

        $this->assertSame(0, $deleted);
    }

    public function test_store_returns_false_when_save_fails(): void
    {
        $device = Mockery::mock(Device::class);
        $device->shouldReceive('save')->once()->andReturn(false);

        $this->assertFalse((new DeviceRepository)->store($device));
    }

    public function test_update_returns_false_when_save_fails(): void
    {
        $device = Mockery::mock(Device::class);
        $device->shouldReceive('save')->once()->andReturn(false);

        $this->assertFalse((new DeviceRepository)->update($device));
    }

    public function test_delete_invokes_device_delete(): void
    {
        // Mutation intent: preserve `$device->delete()` call (~15–18 RemoveMethodCall).
        $device = Mockery::mock(Device::class);
        $device->shouldReceive('delete')->once();

        (new DeviceRepository)->delete($device);
    }

    public function test_store_invokes_save(): void
    {
        // Mutation intent: preserve `return $device->save()` (~10–13 RemoveMethodCall).
        $device = Mockery::mock(Device::class);
        $device->shouldReceive('save')->once()->andReturn(true);

        $this->assertTrue((new DeviceRepository)->store($device));
    }

    public function test_update_invokes_save(): void
    {
        // Mutation intent: preserve `return $device->save()` (~18–22 RemoveMethodCall).
        $device = Mockery::mock(Device::class);
        $device->shouldReceive('save')->once()->andReturn(true);

        $this->assertTrue((new DeviceRepository)->update($device));
    }
}
