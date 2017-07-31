<?php

namespace STS\Contracts\Repository;

use STS\Entities\Device;
use STS\User as UserModel;

interface Devices
{
    public function store(Device $device);

    public function delete(Device $device);

    public function update(Device $device);

    public function getDevices(UserModel $user);

    public function getDeviceBy($key, $value);
}
