<?php

namespace STS\Repository; 

use STS\Entities\Device;
use STS\User;
use Validator;
use STS\Entities\SocialAccount;
use File;

class DeviceRepository
{
    protected $provider;
    public function __construct() {
 
    }

    public function create() 
    {
        return new Device;
    }

    public function delete($device)
    {
        return $device->delete();
    }

    public function update($device)
    {
        return $device->save();
    }

    public function getUserDevices($id)
    {
        return Device::where("user_id", $id)->get();
    }

    public function getByDeviceId($id)
    {
        return Device::where("device_id", $id)->first();
    }
 
    public function getBySessionId($id)
    {
        return Device::where("session_id", $id)->first();
    }

    

}