<?php

namespace STS\Services\Logic;

use STS\User;
use Validator;
use Carbon\Carbon;
use STS\Entities\Device;
use STS\Contracts\Logic\Devices as DeviceLogic;
use STS\Contracts\Repository\Devices as DeviceRepository;

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
            'session_id'  => 'required|string',
            'device_id'   => 'required|string',
            'device_type' => 'required|string',
            'app_version' => 'required|integer',
            'notifications' => 'in:1,0,true,false',
        ]);
    }

    public function validateInput(array $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        }

        return true;
    }

    public function register(User $user, array $data)
    {
        if ($this->validateInput($data)) {
            // $device = $this->deviceRepo->getDeviceBy('session_id', $data['session_id']);
            // if ($device) {
            //     $this->deviceRepo->delete($device);
            // }
            $this->deviceRepo->deleteDevices($user);

            $device = new Device();
            $device->session_id = $data['session_id'];
            $device->device_id = $data['device_id'];
            $device->device_type = $data['device_type'];
            $device->app_version = $data['app_version'];
            $device->last_activity = Carbon::now();
            $device->user_id = $user->id;
            $device->language = 'es';
            $device->notifications = true;
            $this->deviceRepo->store($device);

            return $device;
        }
    }

    public function updateBySession($session_id, array $data)
    {
        //if ($this->validateInput($data)) {
        $device = $this->deviceRepo->getDeviceBy('session_id', $session_id);
        if ($device) {
            $device = $this->fillDevice($device, $data);
            $this->deviceRepo->update($device);

            return $device;
        }
        //}
    }

    public function update($user, $id, array $data)
    {
        if ($this->validateInput($data)) {
            $device = $this->deviceRepo->getDeviceBy('id', $id);
            if ($device && $device->user_id == $user->id) {
                $device = $this->fillDevice($device, $data);
                $this->deviceRepo->update($device);

                return $device;
            } else {
                $this->setErrors(['device_not_found']);
            }
        }
    }

    public function fillDevice($device, $data)
    {
        $device->session_id = $data['session_id'];
        $device->app_version = $data['app_version'];
        $device->last_activity = Carbon::now();
        $device->device_type = $data['device_type'];
        $device->device_id = $data['device_id'];
        $device->notifications = parse_boolean($data['notifications']);
        // $device->language = 'es';

        return $device;
    }

    public function delete($session_id, $user)
    {
        if (is_int($session_id)) {
            $device = $this->deviceRepo->getDeviceBy('id', $session_id);
        } else {
            $device = $this->deviceRepo->getDeviceBy('session_id', $session_id);
        }
        if ($device && $user->id != $device->user_id) {
            $this->deviceRepo->delete($device);
        } else {
            $this->setErrors(['device_not_found']);
        }
    }

    public function getDevices(User $user)
    {
        $this->deviceRepo->getDevices($user);
    }
}
