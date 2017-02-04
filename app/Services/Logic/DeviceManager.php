<?php

namespace STS\Services\Logic; 

use \STS\Contracts\Repository\Devices as DeviceRepository;
use \STS\Contracts\Logic\Devices as DeviceLogic;

use STS\Entities\Device; 
use STS\User;  

class DeviceManager extends BaseManager implements DeviceLogic
{
    protected $deviceRepo;

    public function __construct(DeviceRepository $deviceRepo)
    { 
        $this->deviceRepo = $deviceRepo;   
    }

    public function register(User $user, array $data) {
        $device = $this->deviceRepo->getDeviceBy("session_id", $data['session_id']);
        if ($device) {
            $this->deviceRepo->delete($device);
        }
        
        $device = new Device();
        $device->session_id  = $data["session_id"];    
        $device->device_id   = $data["device_id"];
        $device->device_type = $data["device_type"];
        $device->app_version = $data["app_version"];
        $device->user_id     = $user->id;      
        $this->deviceRepo->store($device);
        return $device;
    }

    public function updateBySession($session_id, array $data) {
        $device = $this->deviceRepo->getDeviceBy("session_id", $session_id);
        if ($device) {
            $device->session_id   = $data["session_id"]; 
            $device->app_version   = $data["app_version"]; 
            $this->deviceRepo->update($device);
        }
        return $device;
    }

    public function delete($session_id) {
        $device = $this->deviceRepo->getDeviceBy("session_id", $session_id);
        if ($device) {
            $this->deviceRepo->delete($device);
        }
    }

    public function getDevices(User $user) {
        $this->deviceRepo->getDevices($user);
    }    
}