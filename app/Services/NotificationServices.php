<?php namespace STS\Services;

use STS\User;
use STS\Entities\Devices;
use Validator; 
use PushNotification;

class NotificationServices {

    public function notificarNuevosViajes($chofer,$viajes) {
        $user = $chofer->usuario;
        //$viajes->lists('id');
        $texto = "Pasajero cerca encontrado.";
        $data = array("url" => "ofrecidos");
        $this->sendNotification($user->id,$texto,$data);
    }

    public function notificar($user,$accion) {
        $texto = "";
        switch ($accion) {
            case 'aceptar':
                $texto = "Un taxi se encuentra en camino.";
                break;
            case 'cancelar':
                if ($user->chofer) {
                    $texto = "El pasajero ha cancelado el viaje.";
                } else {
                    $texto = "La taxista ha cancelado el viaje.";
                }
                break;
            case 'llegando';
                $texto = "Taxi esta llegando a destino.";
                break;    
            default:
                $texto = "";
                break;
        }
        $data = array("url" => $accion);
        $this->sendNotification($user->id,$texto,$data);
    }

    public function sendDummy($id)
    {
         $this->sendNotification($id,"Dummy Notification");
    }

    public function sendNotification($to,$message,$data = null)
    {
        $defaultData = [
            'title' => "Carpoolear",
            //"style" => "inbox",
            //"summaryText" => "Tienes %n% notificaciones"
        ];

        if ($data) {
            $defaultData = array_merge($defaultData,$data);
        }

        $devices = Devices::where("usuario_id", "=", $to)->get();
        foreach($devices as $device) {
            $pos = strpos($device->device_type, "Android");
            if ($pos !== false) {
                $collection = PushNotification::app('android')
                                              ->to($device->device_id)
                                              ->send($message,$defaultData);
                $this->_inspectGoogleResponse($to,$device,$collection);    
            }

            $pos = strpos($device->device_type, "IOS");
            if ($pos !== false) {
                PushNotification::app('ios')
                                ->to($device->device_id)
                                ->send($message,$defaultData);
            }
        }
    }
    
    public function _inspectGoogleResponse($user,$device,$collection) {
        foreach ($collection->pushManager as $push) {
            $response = $push->getAdapter()->getResponse()->getResponse();
            if ($response["canonical_ids"] > 0) {
                $newID =  $response["results"][0]["registration_id"];                
                $d = Devices::where("device_id", "=", $newID)->first();
                if ($d) {
                    $device->delete();
                } else {
                    $hash           = $device->session_id;
                    $usuario        = $device->usuario_id;
                    $device_type    = $device->device_type;
                    $device->delete();    
                    
                    $device                 = new Devices();
                    $device->device_id      = $device_id;
                    $device->session_id     = $hash;
                    $device->usuario_id     = $usuario;
                    $device->device_type    = $device_type;
                    $device->save();                    
                }
                
            }
        }
    }
    

}
