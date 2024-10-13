<?php

namespace STS\Services;

use Google\Client;
use GuzzleHttp\Client as HttpClient;

class FirebaseService
{
    private $googleClient;

    public function __construct()
    {
        // Inicializamos el cliente de Google con la cuenta de servicio
        $this->googleClient = new Client();
        $this->googleClient->setAuthConfig(storage_path('firebase.json'));
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
        $url = 'https://fcm.googleapis.com/v1/projects/carpoolear-test/messages:send';

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
                'Content-Type'  => 'application/json',
            ],
            'json' => $message,
        ]);
        \Log::info($response->getBody());
        return json_decode($response->getBody(), true);
    }
}
