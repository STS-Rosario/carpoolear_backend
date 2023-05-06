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
              
                if ($device->isBrowser()) {
                    $this->sendBrowser($device, $data);
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
       // var_dump(\Config::get('fcm.token'));
        if(\Config::get('fcm.token')==""){
            return;
        }
       
        // El token de registro del dispositivo al que se enviará la notificación
        $device_token = $device->device_id;
      
        // El mensaje que se enviará
        $message = array(
            'title' => 'Carpoolear',
            'body' => $data["message"],
            'icon' => 'https://carpoolear.com.ar/app/static/img/carpoolear_logo.png',
        );

        if (isset($data['url'])) {
            $message['click_action'] =  \Config::get('app.url')."/".$data['url'];
        }
        
        
        // La estructura de datos que se enviará en la solicitud HTTP
        $fields = array(
            'to' => $device_token,
            'notification' => $message
        );
        
        // Codificamos los datos en formato JSON
        $json_data = json_encode($fields);
        
        // Preparamos la solicitud HTTP
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: key=' . \Config::get('fcm.token'),
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        
        // Ejecutamos la solicitud HTTP y cerramos la conexión
        $result = curl_exec($ch);
        curl_close($ch);

      
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
