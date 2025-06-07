<?php

namespace STS\Services;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoService
{
    private $accessToken;
    private $client;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        if (empty($this->accessToken)) {
            throw new \Exception('MercadoPago access token is not configured');
        }
        MercadoPagoConfig::setAccessToken($this->accessToken);
        $this->client = new PreferenceClient();
    }

    public function createPaymentPreference($trip, $amountInCents)
    {
        if (!isset($amountInCents)) {
            $amountInCents = config('carpoolear.module_trip_creation_payment_amount_cents');
        }

        $preferenceData = [
            "items" => [
                [
                    "title" => "Sellado Carpoolear",
                    "quantity" => 1,
                    "unit_price" => floatval($amountInCents) / 100,
                    "currency_id" => "ARS"
                ]
            ],
            "back_urls" => [
                // "success" => config('app.url') . "/app/trips/{$trip->id}/payment-success",
                // "failure" => config('app.url') . "/app/trips/{$trip->id}/payment-failed",
                // "pending" => config('app.url') . "/app/trips/{$trip->id}/payment-pending",
                "success" => 'https://neutral-crucial-ram.ngrok-free.app/app/trips/'.$trip->id.'/payment-success',
                "failure" => 'https://neutral-crucial-ram.ngrok-free.app/app/trips/'.$trip->id.'/payment-failed',
                "pending" => 'https://neutral-crucial-ram.ngrok-free.app/app/trips/'.$trip->id.'/payment-pending',
            ],
            "auto_return" => "approved",
            'external_reference' => 'Sellado de Viaje ID: ' . $trip->id
        ];

        try {
            $requestOptions = new RequestOptions();
            $requestOptions->setAccessToken($this->accessToken);
            
            $preference = $this->client->create($preferenceData, $requestOptions);
            return $preference;
        } catch (MPApiException $e) {
            \Log::error('MercadoPago API Error:', [
                'message' => $e->getMessage(),
                'status' => $e->getApiResponse()->getStatusCode(),
                'response' => $e->getApiResponse()->getContent()
            ]);
            throw $e;
        }
    }
} 