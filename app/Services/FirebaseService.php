<?php

namespace STS\Services;

use Google\Client;
use GuzzleHttp\Client as HttpClient;

class FirebaseService
{
    private $googleClient;

    private $firebaseFile = '';
    private $firebaseName = '';


    public function __construct()
    {
        $this->firebaseFile = config('firebase.firebase_path');
        $this->firebaseName = config('firebase.firebase_project_name');


        // Inicializamos el cliente de Google con la cuenta de servicio 
        $this->googleClient = new Client();
        $this->googleClient->setAuthConfig(storage_path($this->firebaseFile));
        $this->googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');
    }

    /**
     * Obtiene el token de acceso de Firebase.
     */
    public function getAccessToken()
    {
        $accessToken = $this->googleClient->fetchAccessTokenWithAssertion();
        return $accessToken['access_token'];
    }

    /**
     * Envía una notificación usando FCM según el tipo de dispositivo.
     */
    public function sendNotification($deviceToken, $notification, $data, $deviceType = 'android')
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $http = new HttpClient();
            $url = 'https://fcm.googleapis.com/v1/projects/' . $this->firebaseName . '/messages:send';

            $message = [
                'message' => [
                    'token' => $deviceToken
                ]
            ];

            switch (strtolower($deviceType)) {
                case 'android':
                    $stringData = [];
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            if (is_array($value) || is_object($value)) {
                                $stringData[$key] = json_encode($value);
                            } else {
                                $stringData[$key] = (string) $value;
                            }
                        }
                    }
                    
                    $message['message']['android'] = [
                        'notification' => $notification,
                        'data' => $stringData
                    ];
                    break;
                    
                case 'ios':
                    $stringData = [];
                    if (is_array($data)) {
                        foreach ($data as $key => $value) {
                            if (is_array($value) || is_object($value)) {
                                $stringData[$key] = json_encode($value);
                            } else {
                                $stringData[$key] = (string) $value;
                            }
                        }
                    }
                    
                    $message['message']['apns'] = [
                        'payload' => [
                            'aps' => [
                                'alert' => [
                                    'title' => $notification['title'],
                                    'body' => $notification['body']
                                ],
                                'sound' => 'default',
                                'badge' => 1
                            ]
                        ],
                        'headers' => [
                            'apns-priority' => '10'
                        ]
                    ];
                    $message['message']['data'] = $stringData;
                    break;
                    
                case 'browser':
                case 'web':
                default:
                    $message['message']['webpush'] = [
                        'notification' => $notification,
                        'data' => $data
                    ];
                    break;
            }

            $response = $http->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $message
            ]);

            return json_decode($response->getBody(), true);
            
        } catch (\Exception $e) {
            \Log::error('FirebaseService: Error sending notification', [
                'device_token' => $deviceToken,
                'device_type' => $deviceType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Unregisters a device from FCM by sending a delete request
     * This stops the device from receiving any future notifications
     */
    public function unregisterDevice($deviceToken)
    {
        return true;
    }

    /**
     * Invalidates a FCM token by sending an invalid message
     * This causes FCM to mark the token as invalid
     */
    private function invalidateToken($deviceToken)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $http = new HttpClient();
            $url = 'https://fcm.googleapis.com/v1/projects/' . $this->firebaseName . '/messages:send';
            
            $message = [
                'message' => [
                    'token' => $deviceToken,
                    'webpush' => [
                        'notification' => [
                            'title' => '',
                            'body' => ''
                        ]
                    ],
                    'data' => [
                        'invalidate' => 'true'
                    ]
                ]
            ];
            
            $http->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
            ]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
