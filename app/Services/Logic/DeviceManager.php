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

    public function validator(array $data)
    {
        return Validator::make($data, [
            'session_id' => 'required|string',
            'device_id' => 'required|string',
            'device_type' => 'required|string',
            'app_version' => 'required|integer',
        ]);   
    }

    public function register(User $user, array $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else {
            $device = $this->deviceRepo->getDeviceBy('session_id', $data['session_id']);
            if ($device) {
                $this->deviceRepo->delete($device);
            }
            
            $device = new Device();
            $device->session_id  = $data['session_id'];
            $device->device_id   = $data['device_id'];
            $device->device_type = $data['device_type'];
            $device->app_version = $data['app_version'];
            $device->user_id     = $user->id;
            $this->deviceRepo->store($device);
            return $device;
        }
    }

    public function updateBySession($session_id, array $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());
            return null;
        } else {
            $device = $this->deviceRepo->getDeviceBy('session_id', $session_id);
            if ($device) {
                $device->session_id     = $data['session_id'];
                $device->app_version    = $data['app_version'];
                $device->device_type    = $data['device_type'];
                $device->device_id      = $data['device_id'];
                $this->deviceRepo->update($device);
            }
            return $device;
        }
    }

    public function delete($session_id)
    {
        $device = $this->deviceRepo->getDeviceBy('session_id', $session_id);
        if ($device) {
            $this->deviceRepo->delete($device);
        }
    }

    public function getDevices(User $user)
    {
        $this->deviceRepo->getDevices($user);
    }
}
