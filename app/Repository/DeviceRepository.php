<?php

namespace STS\Repository;

use STS\Models\User;
use STS\Models\Device;

class DeviceRepository
{
    public function store(Device $device)
    {
        return $device->save();
    }

    public function delete(Device $device)
    {
        $device->delete();
    }

    public function update(Device $device)
    {
        return $device->save();
    }

    public function getDevices(User $user)
    {
        return Device::where('user_id', $user->id)->get();
    }

    public function getDeviceBy($key, $value)
    {
        return Device::where($key, $value)->first();
    }

    public function deleteDevices(User $user)
    {
        return Device::where('user_id', $user->id)->delete();
    }
}
