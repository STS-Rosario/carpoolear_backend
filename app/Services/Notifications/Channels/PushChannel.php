<?php

namespace STS\Services\Notifications\Channels;

use STS\Entities\Device;

class PushChannel
{
    protected $android_actions = [];

    public function __construct()
    {
    }

    public function send($notification, $user)
    {
        foreach ($user->devices as $device) {
            $data = $this->getData($notification, $user, $device);
            $data['extras'] = $this->getExtraData($notification);
          
            if ($device->notifications) {
                if ($device->isAndroid()) {
                    $this->sendAndroid($device, $data);
                }
                if ($device->isIOS()) {
                    $this->sendIOS($device, $data);
                }
              
                if ($device->isWeb()) {
                    $this->sendWeb($device, $data);
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

    public function sendWeb($device, $data)
    {
      //var_dump($device);
      //var_dump($data);
       $device->session_id = $device->session_id."listo";
       $device->save();
      
    }


    public function sendAndroid($device, $data)
    {
        $message = $data['message'];

        $defaultData = [
            'title' => isset($data['title']) ? $data['title'] : 'Carpoolear',
        ];

        if (isset($data['sound'])) {
            $defaultData['soundname'] = $data['sound'];
        }

        if (isset($data['url'])) {
            $defaultData['url'] = $data['url'];
        }

        if (isset($data['type'])) {
            $defaultData['type'] = $data['type'];
        }

        if (isset($data['extras'])) {
            $defaultData['extras'] = $data['extras'];
        }

        if (isset($data['action'])) {
            $defaultData['actions'] = $android_actions[$data['action']];
        }
        if (! isset($data['time_to_live'])) {
            $defaultData['time_to_live'] = 2419200;
        }

        $defaultData['image'] = isset($data['image']) ? $data['image'] : 'www/logo.png';

        $collection = \PushNotification::app('android')
                                    ->to($device->device_id)
                                    ->send($message, $defaultData);

        $this->_inspectGoogleResponse($device, $collection);
    }

    public function sendIOS($device, $data)
    {
        $message = $data['message'];

        $defaultData = [
            'title' => isset($data['title']) ? $data['title'] : 'Carpoolear',
        ];

        if (isset($data['sound'])) {
            $defaultData['sound'] = 'www/audio/'.$data['sound'].'.wav';
        }

        $defaultData['custom'] = [];
        if (isset($data['url'])) {
            $defaultData['custom']['url'] = $data['url'];
        }

        if (isset($data['extras'])) {
            $defaultData['custom']['extras'] = $data['extras'];
        }

        if (isset($data['action'])) {
            $defaultData['category'] = $data['action'];
        }

        $collection = \PushNotification::app('ios')
                        ->to($device->device_id)
                        ->send($message, $defaultData);
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
