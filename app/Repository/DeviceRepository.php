<?php

namespace STS\Repository;

use STS\User;
use STS\Entities\Device;
use STS\Contracts\Repository\Devices as DeviceRepo;

class DeviceRepository implements DeviceRepo
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
}
