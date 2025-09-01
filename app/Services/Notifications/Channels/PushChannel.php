<?php

namespace STS\Services\Notifications\Channels;

use STS\Services\FirebaseService;
use STS\Models\Device;
use Carbon\Carbon;

class PushChannel
{
    protected $android_actions = [];

    public function __construct()
    {
    }

    public function send($notification, $user)
    {
        $devicesFiltered = $user->devices->filter(function ($device)  {
            $activity_days = \Config::get('carpoolear.send_push_notifications_to_device_activity_days');
            if ($activity_days==0) {
               return true;
            }
            if ($device->last_activity==null) {
                return false;
            }
            return $device->last_activity->greaterThan(Carbon::now()->subDays($activity_days));
        });

        foreach ($devicesFiltered as $device) {

            $data = $this->getData($notification, $user, $device);
            $data['extras'] = $this->getExtraData($notification);
          
            if ($device->notifications) {
                if ($device->isAndroid()) {
                    $this->sendAndroid($device, $data);
                    return;
                }
                if ($device->isIOS()) {
                    $this->sendIOS($device, $data);
                    return;
                }
                elseif ($device->isBrowser()) {
                    $this->sendBrowser($device, $data);
                    return;
                } else {
                    \Log::warning('PushChannel: Device type not supported for push', [
                        'device_type' => $device->device_type,
                        'is_android' => $device->isAndroid(),
                        'is_ios' => $device->isIOS(),
                        'is_browser' => $device->isBrowser()
                    ]);
                }
            }
        }
    }

    public function getData($notification, $user, $device)
    {
        if (method_exists($notification, 'toPush')) {
            return $notification->toPush($user, $device);
        } else {
            throw new \Exception("Method toPush does't exists");
        }
    }

    public function getExtraData($notification)
    {
        if (method_exists($notification, 'getExtras')) {
            return $notification->getExtras();
        }
    }

    public function sendBrowser($device, $data)
    { 
        $firebase = new FirebaseService();
       
        $device_token = $device->device_id;
      
        $message = array(
            'title' => 'Carpoolear',
            'body' => $data["message"],
            'icon' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png'
        ); 

        if (isset($data['url'])) {
            $message['click_action'] = $data['url'];
        } 
        
        $firebase->sendNotification($device_token, $message, $data["extras"], 'browser');
    }


    public function sendAndroid($device, $data)
    {
        try {
            $firebase = new FirebaseService();
            
            $device_token = $device->device_id;
            
            $message = array(
                'title' => isset($data['title']) ? $data['title'] : 'Carpoolear',
                'body' => $data['message'],
                'icon' => isset($data['image']) ? $data['image'] : 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png'
            ); 

            if (isset($data['url'])) {
                $message['click_action'] = $data['url'];
            }

            $dataPayload = [];
            if (isset($data['type'])) {
                $dataPayload['type'] = (string) $data['type'];
            }
            if (isset($data['extras'])) {
                foreach ($data['extras'] as $key => $value) {
                    $dataPayload[$key] = (string) $value;
                }
            }
            if (isset($data['url'])) {
                $dataPayload['url'] = (string) $data['url'];
            }

            $response = $firebase->sendNotification($device_token, $message, $dataPayload, 'android');
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('PushChannel: sendAndroid error', [
                'device_id' => $device->id,
                'device_token' => $device->device_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendIOS($device, $data)
    {
        try {
            $firebase = new FirebaseService();
            
            $device_token = $device->device_id;
            
            $message = array(
                'title' => isset($data['title']) ? $data['title'] : 'Carpoolear',
                'body' => $data['message'],
                'icon' => isset($data['image']) ? $data['image'] : 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png'
            ); 

            $dataPayload = [];
            if (isset($data['type'])) {
                $dataPayload['type'] = (string) $data['type'];
            }
            if (isset($data['extras'])) {
                foreach ($data['extras'] as $key => $value) {
                    $dataPayload[$key] = (string) $value;
                }
            }
            if (isset($data['url'])) {
                $dataPayload['url'] = (string) $data['url'];
            }

            $response = $firebase->sendNotification($device_token, $message, $dataPayload, 'ios');
            
            return $response;
        } catch (\Exception $e) {
            \Log::error('PushChannel: sendIOS error', [
                'device_id' => $device->id,
                'device_token' => $device->device_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function _inspectGoogleResponse($device, $collection)
    {
        foreach ($collection->pushManager as $push) {
            $response = $push->getAdapter()->getResponse()->getResponse();
            console_log($response);
            if ($response['canonical_ids'] > 0) {
                $newID = $response['results'][0]['registration_id'];
                $d = Device::where('device_id', $newID)->first();
                if ($d) {
                    $device->delete();
                } else {
                    $hash = $device->session_id;
                    $usuario = $device->usuario_id;
                    $device_type = $device->device_type;
                    $device->delete();

                    $device = new Device();
                    $device->device_id = $device_id;
                    $device->session_id = $hash;
                    $device->usuario_id = $usuario;
                    $device->device_type = $device_type;
                    $device->save();
                }
            }
        }
    }
}
