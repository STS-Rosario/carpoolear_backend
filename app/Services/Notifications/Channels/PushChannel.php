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
            if ($device->isAndroid()) {
                $this->sendAndroid($device, $data);
            }
            if ($device->isIOS()) {
                $this->sendIOS($device, $data);
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

        if (isset($data['extras'])) {
            $defaultData['extras'] = $data['extras'];
        }

        if (isset($data['action'])) {
            $defaultData['actions'] = $android_actions[$data['action']];
        }

        $defaultData['image'] = isset($data['image']) ? $data['image'] : 'www/logo.png';

        $collection = PushNotification::app('android')
                                    ->to($device->device_id)
                                    ->send($message, $defaultData);
        $this->_inspectGoogleResponse($to, $device, $collection);
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

        $collection = PushNotification::app('ios')
                        ->to($device->device_id)
                        ->send($message, $defaultData);
    }

    public function _inspectGoogleResponse($device, $collection)
    {
        foreach ($collection->pushManager as $push) {
            $response = $push->getAdapter()->getResponse()->getResponse();
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

                    $device = new Devices();
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
