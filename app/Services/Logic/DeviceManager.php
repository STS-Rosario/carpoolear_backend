<?php

namespace STS\Services\Logic;

use STS\Repository\DeviceRepository;
use STS\Models\User;
use Validator;
use Carbon\Carbon;
use STS\Models\Device; 

class DeviceManager extends BaseManager
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
            // 'device_type' => 'required|string',
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
            // Check if device already exists for this session
            $existingDevice = $this->deviceRepo->getDeviceBy('session_id', $data['session_id']);
            if ($existingDevice) {
                // Update existing device
                $existingDevice = $this->fillDevice($existingDevice, $data);
                $this->deviceRepo->update($existingDevice);
                return $existingDevice;
            }
            
            // CRITICAL: Check if device_id already exists for ANY user
            // This ensures device-level account isolation - one device = one user
            $deviceByDeviceId = $this->deviceRepo->getDeviceBy('device_id', $data['device_id']);
            if ($deviceByDeviceId) {
                if ($deviceByDeviceId->user_id == $user->id) {
                    // Same user, same device - update with new session
                    $deviceByDeviceId = $this->fillDevice($deviceByDeviceId, $data);
                    $this->deviceRepo->update($deviceByDeviceId);
                    return $deviceByDeviceId;
                } else {
                    // Device belongs to different user - remove it first
                    // This prevents cross-account notifications
                    $this->deviceRepo->delete($deviceByDeviceId);
                }
            }
            
            // Create new device
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
        // Only update fields that are provided in the data array
        if (isset($data['session_id'])) {
            $device->session_id = $data['session_id'];
        }
        
        if (isset($data['app_version'])) {
            $device->app_version = $data['app_version'];
        }
        
        if (isset($data['device_type'])) {
            $device->device_type = $data['device_type'];
        }
        
        if (isset($data['device_id'])) {
            $device->device_id = $data['device_id'];
        }
        
        if (isset($data['notifications'])) {
            $device->notifications = parse_boolean($data['notifications']);
        }
        
        // Always update last_activity
        $device->last_activity = Carbon::now();
        
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
        return $this->deviceRepo->getDevices($user);
    }

    /**
     * Clean up inactive devices for a user
     * @param User $user
     * @param int $daysInactive Number of days of inactivity before cleanup
     * @return int Number of devices removed
     */
    public function cleanupInactiveDevices(User $user, $daysInactive = 30)
    {
        $cutoffDate = Carbon::now()->subDays($daysInactive);
        $inactiveDevices = Device::where('user_id', $user->id)
            ->where('last_activity', '<', $cutoffDate)
            ->get();
        
        $count = 0;
        foreach ($inactiveDevices as $device) {
            $this->deviceRepo->delete($device);
            $count++;
        }
        
        return $count;
    }

    /**
     * Get active devices count for a user
     * @param User $user
     * @return int
     */
    public function getActiveDevicesCount(User $user)
    {
        return Device::where('user_id', $user->id)
            ->where('notifications', true)
            ->count();
    }

    /**
     * Logout/cleanup a specific device by session_id
     * @param string $session_id
     * @param User $user
     * @return bool
     */
    public function logoutDevice($session_id, User $user)
    {
        $device = $this->deviceRepo->getDeviceBy('session_id', $session_id);
        if ($device && $device->user_id == $user->id) {
            // First, unregister from FCM to stop notifications
            $firebaseService = new \STS\Services\FirebaseService();
            $firebaseService->unregisterDevice($device->device_id);
            
            // Then delete the device record
            $this->deviceRepo->delete($device);
            return true;
        }
        return false;
    }

    /**
     * Logout all devices for a user (useful for account logout)
     * @param User $user
     * @return int Number of devices removed
     */
    public function logoutAllDevices(User $user)
    {
        $devices = $this->deviceRepo->getDevices($user);
        $count = 0;
        
        \Log::info('Logging out all devices for user', [
            'user_id' => $user->id,
            'device_count' => $devices->count()
        ]);
        
        foreach ($devices as $device) {
            try {
                \Log::info('Processing device for logout', [
                    'device_id' => $device->id,
                    'device_token' => $device->device_id,
                    'session_id' => $device->session_id,
                    'device_type' => $device->device_type
                ]);
                
                // Unregister from FCM first
                $firebaseService = new \STS\Services\FirebaseService();
                $unregisterResult = $firebaseService->unregisterDevice($device->device_id);
                
                \Log::info('FCM unregister result', [
                    'device_token' => $device->device_id,
                    'success' => $unregisterResult
                ]);
                
                // Then delete the device record
                $this->deviceRepo->delete($device);
                $count++;
                
                \Log::info('Device successfully logged out', [
                    'device_id' => $device->id,
                    'device_token' => $device->device_id
                ]);
                
            } catch (\Exception $e) {
                \Log::error('Failed to logout device', [
                    'device_id' => $device->id,
                    'device_token' => $device->device_id,
                    'error' => $e->getMessage()
                ]);
                
                // Still delete the device record even if FCM unregister fails
                $this->deviceRepo->delete($device);
                $count++;
            }
        }
        
        \Log::info('Logout all devices completed', [
            'user_id' => $user->id,
            'devices_removed' => $count
        ]);
        
        return $count;
    }
}
