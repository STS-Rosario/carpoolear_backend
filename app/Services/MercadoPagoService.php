<?php

namespace STS\Services;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use STS\Models\Campaign;

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

    /**
     * Create a payment preference with the given data
     */
    public function createPaymentPreference(array $preferenceData)
    {
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

    /**
     * Create a payment preference specifically for trip sellado
     */
    public function createPaymentPreferenceForSellado($trip, $amountInCents = null)
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
                "success" => 'https://neutral-crucial-ram.ngrok-free.app/app/trips/'.$trip->id,
                "failure" => 'https://neutral-crucial-ram.ngrok-free.app/app/trips/'.$trip->id,
                "pending" => 'https://neutral-crucial-ram.ngrok-free.app/app/trips/'.$trip->id,
            ],
            "auto_return" => "approved",
            'external_reference' => 'Sellado de Viaje ID: ' . $trip->id
        ];

        return $this->createPaymentPreference($preferenceData);
    }

    /**
     * Create a payment preference for campaign donations
     */
    public function createPaymentPreferenceForCampaignDonation(int $campaignId, int $amountInCents, ?int $userId = null, ?int $rewardId = null, ?int $donationId = null)
    {
        $campaign = Campaign::findOrFail($campaignId);
        
        $preferenceData = [
            "items" => [
                [
                    "title" => "Donación para Carpoolear: " . $campaign->title,
                    "quantity" => 1,
                    "unit_price" => floatval($amountInCents) / 100,
                    "currency_id" => "ARS"
                ]
            ],
            "back_urls" => [
                "success" => config('app.url') . "/campaigns/{$campaign->slug}?result=success",
                "failure" => config('app.url') . "/campaigns/{$campaign->slug}?result=failed",
                "pending" => config('app.url') . "/campaigns/{$campaign->slug}?result=pending",
            ],
            "auto_return" => "approved",
            'external_reference' => $this->createHashedExternalReference(
                $campaign->id,
                $campaign->payment_slug,
                $rewardId ?? 0,
                $userId ?? 'Anonymous',
                $donationId ?? 0
            )
        ];

        return $this->createPaymentPreference($preferenceData);
    }

    /**
     * Create a hashed external reference for campaign donations
     * Format: {hash}:{base64_encoded_data}
     */
    private function createHashedExternalReference(int $campaignId, string $slug, int $rewardId, $userId, int $donationId): string
    {
        $referenceString = sprintf(
            'Donación Campaña ID: %d; Slug: %s; Reward ID: %d; User ID: %s; Donation ID: %d',
            $campaignId,
            $slug,
            $rewardId,
            $userId,
            $donationId
        );

        $salt = config('services.mercadopago.reference_salt', 'carpoolear_2024_secure_salt');
        $hash = hash('sha256', $referenceString . $salt);
        $encodedData = base64_encode($referenceString);

        return $hash . ':' . $encodedData;
    }
}  