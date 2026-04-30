<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use STS\Models\Device;
use STS\Models\User;
use STS\Repository\DeviceRepository;
use STS\Services\Logic\DeviceManager;
use Tests\TestCase;

class DeviceManagerTest extends TestCase
{
    private function manager(): DeviceManager
    {
        return new DeviceManager(new DeviceRepository);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'session_id' => 'sess-'.uniqid('', true),
            'device_id' => 'dev-'.uniqid('', true),
            'device_type' => 'android',
            'app_version' => 12,
            'notifications' => 1,
        ], $overrides);
    }

    public function test_validator_requires_session_device_and_app_version(): void
    {
        $v = $this->manager()->validator([]);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('session_id'));
        $this->assertTrue($v->errors()->has('device_id'));
        $this->assertTrue($v->errors()->has('app_version'));
    }

    public function test_validator_rejects_invalid_notifications_value(): void
    {
        $v = $this->manager()->validator($this->validPayload([
            'notifications' => 'maybe',
        ]));

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('notifications'));
    }

    public function test_validate_input_sets_errors_on_failure(): void
    {
        $manager = $this->manager();
        $this->assertNull($manager->validateInput([]));
        $this->assertNotNull($manager->getErrors());
    }

    public function test_register_creates_device_for_valid_payload(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();
        $data = $this->validPayload();

        $device = $manager->register($user, $data);

        $this->assertInstanceOf(Device::class, $device);
        $this->assertDatabaseHas('users_devices', [
            'user_id' => $user->id,
            'session_id' => $data['session_id'],
            'device_id' => $data['device_id'],
        ]);
    }

    public function test_register_updates_existing_row_when_session_id_matches(): void
    {
        $user = User::factory()->create();
        $session = 'sess-fixed-'.uniqid('', true);
        $first = $this->manager()->register($user, $this->validPayload([
            'session_id' => $session,
            'device_id' => 'dev-first-'.uniqid('', true),
            'app_version' => 1,
        ]));
        $second = $this->manager()->register($user, $this->validPayload([
            'session_id' => $session,
            'device_id' => 'dev-second-'.uniqid('', true),
            'app_version' => 2,
        ]));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, (int) $second->fresh()->app_version);
    }

    public function test_register_with_existing_session_does_not_delete_other_users_same_device_id(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $session = 'sess-fixed-'.uniqid('', true);
        $sharedDeviceId = 'shared-dev-'.uniqid('', true);

        $existing = $this->manager()->register($owner, $this->validPayload([
            'session_id' => $session,
            'device_id' => 'owner-original-'.uniqid('', true),
            'app_version' => 1,
        ]));

        $otherDevice = $this->manager()->register($other, $this->validPayload([
            'session_id' => 'other-sess-'.uniqid('', true),
            'device_id' => $sharedDeviceId,
            'app_version' => 1,
        ]));

        $updated = $this->manager()->register($owner, $this->validPayload([
            'session_id' => $session,
            'device_id' => $sharedDeviceId,
            'app_version' => 9,
        ]));

        $this->assertSame($existing->id, $updated->id);
        $this->assertNotNull(Device::query()->find($otherDevice->id));
        $this->assertSame(2, Device::query()->count());
    }

    public function test_register_updates_when_same_device_id_and_same_user(): void
    {
        $user = User::factory()->create();
        $deviceId = 'dev-shared-'.uniqid('', true);
        $a = $this->manager()->register($user, $this->validPayload([
            'session_id' => 's1-'.uniqid('', true),
            'device_id' => $deviceId,
            'app_version' => 3,
        ]));
        $second = $this->validPayload([
            'session_id' => 's2-'.uniqid('', true),
            'device_id' => $deviceId,
            'app_version' => 4,
        ]);
        $b = $this->manager()->register($user, $second);

        $this->assertSame($a->id, $b->id);
        $this->assertSame($second['session_id'], $b->fresh()->session_id);
        $this->assertSame(4, (int) $b->fresh()->app_version);
    }

    public function test_register_deletes_other_users_device_when_device_id_collides(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $deviceId = 'dev-collision-'.uniqid('', true);

        $this->manager()->register($owner, $this->validPayload([
            'session_id' => 'own-sess-'.uniqid('', true),
            'device_id' => $deviceId,
        ]));

        $this->manager()->register($intruder, $this->validPayload([
            'session_id' => 'intr-sess-'.uniqid('', true),
            'device_id' => $deviceId,
        ]));

        $this->assertSame(0, Device::query()->where('user_id', $owner->id)->count());
        $this->assertSame(1, Device::query()->where('user_id', $intruder->id)->count());
        $this->assertSame($deviceId, Device::query()->where('user_id', $intruder->id)->value('device_id'));
    }

    public function test_update_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = $this->manager()->register($owner, $this->validPayload());

        $manager = $this->manager();
        $this->assertNull($manager->update($other, $device->id, $this->validPayload(['app_version' => 99])));
        $this->assertEqualsCanonicalizing(['device_not_found'], $manager->getErrors());
    }

    public function test_update_accepts_equivalent_scalar_owner_ids(): void
    {
        $owner = User::factory()->create();
        $device = $this->manager()->register($owner, $this->validPayload(['app_version' => 2]));
        $sameOwnerAsStringId = new User;
        $sameOwnerAsStringId->id = (string) $owner->id;

        $updated = $this->manager()->update($sameOwnerAsStringId, $device->id, $this->validPayload([
            'app_version' => 10,
        ]));

        $this->assertInstanceOf(Device::class, $updated);
        $this->assertSame(10, (int) $updated->fresh()->app_version);
    }

    public function test_update_persists_changes_for_owner(): void
    {
        $user = User::factory()->create();
        $device = $this->manager()->register($user, $this->validPayload(['app_version' => 1]));

        $updated = $this->manager()->update($user, $device->id, $this->validPayload([
            'app_version' => 7,
            'notifications' => 0,
        ]));

        $this->assertSame(7, (int) $updated->fresh()->app_version);
        $this->assertFalse((bool) $updated->fresh()->notifications);
    }

    public function test_update_by_session_updates_without_validation_gate(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload(['app_version' => 5]);
        $device = $this->manager()->register($user, $payload);

        $updated = $this->manager()->updateBySession($payload['session_id'], [
            'app_version' => 8,
        ]);

        $this->assertSame(8, (int) $updated->fresh()->app_version);
    }

    public function test_delete_removes_device_when_caller_is_not_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = $this->manager()->register($owner, $this->validPayload());
        $id = $device->id;

        $this->manager()->delete($device->session_id, $other);

        $this->assertNull(Device::query()->find($id));
    }

    public function test_delete_sets_error_when_caller_is_owner(): void
    {
        $user = User::factory()->create();
        $device = $this->manager()->register($user, $this->validPayload());

        $manager = $this->manager();
        $manager->delete($device->session_id, $user);

        $this->assertTrue(Device::query()->whereKey($device->id)->exists());
        $this->assertEqualsCanonicalizing(['device_not_found'], $manager->getErrors());
    }

    public function test_delete_treats_equivalent_scalar_owner_ids_as_owner(): void
    {
        $owner = User::factory()->create();
        $device = $this->manager()->register($owner, $this->validPayload());
        $sameOwnerAsStringId = new User;
        $sameOwnerAsStringId->id = (string) $owner->id;

        $manager = $this->manager();
        $manager->delete($device->session_id, $sameOwnerAsStringId);

        $this->assertTrue(Device::query()->whereKey($device->id)->exists());
        $this->assertEqualsCanonicalizing(['device_not_found'], $manager->getErrors());
    }

    public function test_get_devices_delegates_to_repository(): void
    {
        $user = User::factory()->create();
        $this->manager()->register($user, $this->validPayload());

        $devices = $this->manager()->getDevices($user);

        $this->assertCount(1, $devices);
    }

    public function test_cleanup_inactive_devices_removes_old_rows(): void
    {
        $user = User::factory()->create();
        $device = $this->manager()->register($user, $this->validPayload());
        $device->forceFill(['last_activity' => Carbon::now()->subDays(60)->toDateString()])->saveQuietly();

        $removed = $this->manager()->cleanupInactiveDevices($user, 30);

        $this->assertSame(1, $removed);
        $this->assertSame(0, Device::query()->where('user_id', $user->id)->count());
    }

    public function test_get_active_devices_count_counts_notifications_enabled(): void
    {
        $user = User::factory()->create();
        $m = $this->manager();
        $m->register($user, $this->validPayload(['notifications' => 1]));
        $m->register($user, $this->validPayload([
            'session_id' => 's2-'.uniqid('', true),
            'device_id' => 'd2-'.uniqid('', true),
            'notifications' => 1,
        ]));

        $this->assertSame(2, $m->getActiveDevicesCount($user));

        $one = Device::where('user_id', $user->id)->first();
        $one->notifications = false;
        $one->save();

        $this->assertSame(1, $m->getActiveDevicesCount($user));
    }

    public function test_logout_device_deletes_when_session_owned_by_user(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload();
        $device = $this->manager()->register($user, $payload);

        $this->assertTrue($this->manager()->logoutDevice($payload['session_id'], $user));
        $this->assertNull(Device::query()->find($device->id));
    }

    public function test_logout_all_devices_removes_all_for_user(): void
    {
        $user = User::factory()->create();
        $m = $this->manager();
        $m->register($user, $this->validPayload(['session_id' => 'a-'.uniqid('', true), 'device_id' => 'da-'.uniqid('', true)]));
        $m->register($user, $this->validPayload(['session_id' => 'b-'.uniqid('', true), 'device_id' => 'db-'.uniqid('', true)]));

        $count = $m->logoutAllDevices($user);

        $this->assertSame(2, $count);
        $this->assertSame(0, Device::query()->where('user_id', $user->id)->count());
    }
}
