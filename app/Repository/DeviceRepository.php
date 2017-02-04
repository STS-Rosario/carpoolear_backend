<?php

namespace STS\Repository; 

use STS\Contracts\Repository\Devices as DeviceRepo;

use STS\Entities\Device;
use STS\User;
use Validator;
use STS\Entities\SocialAccount;
use File;

class DeviceRepository implements DeviceRepo
{ 

    public function store(Device $device) {
        return $device->save();
    }

    public function delete(Device $device) {
        $device->delete();
    }

    public function update(Device $device) {
        return $device->save();
    }

    public function getDevices(User $user) {
        return Device::where("user_id", $user->id)->get();
    }

    public function getDeviceBy($key, $value) {
        return Device::where($key, $value)->first();
    }
    
}