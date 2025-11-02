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
                try {
                    if ($device->isAndroid()) {
                        $this->sendAndroid($device, $data);
                    } elseif ($device->isIOS()) {
                        $this->sendIOS($device, $data);
                    } elseif ($device->isBrowser()) {
                        $this->sendBrowser($device, $data);
                    }
                } catch (\Exception $e) {
                    \Log::error('PushChannel: Error sending push notification', [
                        'device_id' => substr($device->device_id, 0, 20) . '...',
                        'device_type' => $device->device_type,
                        'error' => $e->getMessage()
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
            // Create APNs payload
            $payload = [
                'aps' => [
                    'alert' => [
                        'title' => isset($data['title']) ? $data['title'] : 'Carpoolear',
                        'body' => $data['message']
                    ],
                    'sound' => 'default',
                    'badge' => 1
                ]
            ];

            // Add custom data
            if (isset($data['extras'])) {
                foreach ($data['extras'] as $key => $value) {
                    $payload[$key] = $value;
                }
            }

            if (isset($data['url'])) {
                $payload['url'] = $data['url'];
            }

            // Send via APNs
            $result = $this->sendAPNsNotification($device->device_id, $payload);
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('PushChannel: sendIOS error', [
                'device_id' => $device->id,
                'device_token' => $device->device_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function sendAPNsNotification($deviceToken, $payload)
    {
        // APNs configuration
        $apnsCert = config('push-notification.ios.certificate');
        $apnsPassphrase = config('push-notification.ios.passPhrase');
        $apnsEnvironment = config('push-notification.ios.environment');

        // Check if certificate exists
        if (!file_exists($apnsCert)) {
            throw new \Exception("APNs certificate not found: {$apnsCert}");
        }
        
        // Use HTTP/2 APNs (more modern and reliable)
        $url = ($apnsEnvironment === 'production') 
            ? 'https://api.push.apple.com/3/device/' . $deviceToken
            : 'https://api.development.push.apple.com/3/device/' . $deviceToken;

        // Validate device token format
        if (!ctype_xdigit($deviceToken) || strlen($deviceToken) != 64) {
            throw new \Exception("Invalid APNs device token format. Expected 64 hex characters, got: " . strlen($deviceToken));
        }

        try {
            // Use cURL for HTTP/2 APNs
            $ch = curl_init();
            
            // Check if we have a PEM or P12 file
            $isPem = pathinfo($apnsCert, PATHINFO_EXTENSION) === 'pem';
            $isP12 = pathinfo($apnsCert, PATHINFO_EXTENSION) === 'p12';

            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'apns-topic: com.sts.carpoolear', // Your app bundle ID
                    'apns-priority: 10'
                ],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ];

            if ($isPem) {
                $curlOptions[CURLOPT_SSLCERT] = $apnsCert;
                $curlOptions[CURLOPT_SSLCERTPASSWD] = $apnsPassphrase ?: '';
            } elseif ($isP12) {
                $curlOptions[CURLOPT_SSLCERTTYPE] = 'P12';
                $curlOptions[CURLOPT_SSLCERT] = $apnsCert;
                $curlOptions[CURLOPT_SSLCERTPASSWD] = $apnsPassphrase ?: '';
            } else {
                throw new \Exception("Unsupported certificate format. Use .pem or .p12 files.");
            }

            curl_setopt_array($ch, $curlOptions);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                throw new \Exception("cURL error: {$error}");
            }

            if ($httpCode !== 200) {
                throw new \Exception("APNs returned HTTP {$httpCode}: {$response}");
            }

            return ['success' => true, 'http_code' => $httpCode, 'response' => $response];

        } catch (\Exception $e) {
            throw $e;
        }
    }

}
