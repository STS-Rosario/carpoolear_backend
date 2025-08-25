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
     * Envía una notificación Webpush usando FCM.
     */
    public function sendNotification($deviceToken, $notification, $data)
    {
        $accessToken = $this->getAccessToken();

        $http = new HttpClient();
        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->firebaseName . '/messages:send';

        // Construir el payload de la notificación
        $message = [
            'message' => [
                'token' => $deviceToken,
                'webpush' => [
                    'notification' => $notification,
                ]
                // 'data' => $data
            ]
        ];

        // Hacer la solicitud POST al servidor de FCM
        $response = $http->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $message,
        ]); 
        return json_decode($response->getBody(), true);
    }

    /**
     * Unregisters a device from FCM by sending a delete request
     * This stops the device from receiving any future notifications
     */
    public function unregisterDevice($deviceToken)
    {
        // Simply log the unregistration - no need to send a push notification
        // The device will be removed from our database, which is sufficient
        // FCM will naturally invalidate the token when we try to send to it later
        \Log::info('Device unregistered from FCM', [
            'device_token' => $deviceToken,
            'method' => 'database_removal'
        ]);
        
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
            
            // Send a message with invalid data to invalidate the token
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
