<?php

namespace STS\Services;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;
use STS\Models\Campaign;

class MercadoPagoService
{
    private $accessToken;
    private $client;
    private $orderClient;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        if (!empty($this->accessToken)) {
            MercadoPagoConfig::setAccessToken($this->accessToken);
            $this->client = new PreferenceClient();
        }
    }

    private function ensureConfigured(): void
    {
        if (empty($this->accessToken)) {
            throw new \Exception('MercadoPago access token is not configured');
        }
        MercadoPagoConfig::setAccessToken($this->accessToken);
        $this->client = new PreferenceClient();
        $this->orderClient = new OrderClient();
    }

    /**
     * Create a payment preference with the given data
     */
    public function createPaymentPreference(array $preferenceData)
    {
        $this->ensureConfigured();
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

        $baseUrl = rtrim(config('carpoolear.frontend_url'), '/');
        $selladoUrls = [
            "success" => $baseUrl . '/app/trips/' . $trip->id,
            "failure" => $baseUrl . '/app/trips/' . $trip->id,
            "pending" => $baseUrl . '/app/trips/' . $trip->id,
        ];
        $preferenceData = [
            "items" => [
                [
                    "title" => "Sellado Carpoolear",
                    "quantity" => 1,
                    "unit_price" => floatval($amountInCents) / 100,
                    "currency_id" => "ARS"
                ]
            ],
            "back_urls" => $selladoUrls,
            "back_url" => $selladoUrls,
            "auto_return" => "approved",
            'external_reference' => $this->createHashedExternalReferenceForSellado((int) $trip->id)
        ];

        return $this->createPaymentPreference($preferenceData);
    }

    /**
     * Create a payment preference for campaign donations
     */
    public function createPaymentPreferenceForCampaignDonation(int $campaignId, int $amountInCents, ?int $userId = null, ?int $rewardId = null, ?int $donationId = null)
    {
        $campaign = Campaign::findOrFail($campaignId);
        
        $campaignUrls = [
            "success" => config('app.url') . "/campaigns/{$campaign->slug}?result=success",
            "failure" => config('app.url') . "/campaigns/{$campaign->slug}?result=failed",
            "pending" => config('app.url') . "/campaigns/{$campaign->slug}?result=pending",
        ];
        $preferenceData = [
            "items" => [
                [
                    "title" => "Donación para Carpoolear: " . $campaign->title,
                    "quantity" => 1,
                    "unit_price" => floatval($amountInCents) / 100,
                    "currency_id" => "ARS"
                ]
            ],
            "back_urls" => $campaignUrls,
            "back_url" => $campaignUrls,
            "auto_return" => "approved",
            'external_reference' => $this->createHashedExternalReferenceForCampaignDonation(
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
     * Build a hashed external reference from a plain reference string (encoding + salting).
     * Format: {hash}:{base64_encoded_data}. Used by campaign donations and sellado de viaje.
     */
    private function buildHashedReference(string $referenceString): string
    {
        $salt = config('services.mercadopago.reference_salt', 'carpoolear_2024_secure_salt');
        $hash = hash('sha256', $referenceString . $salt);
        $encodedData = base64_encode($referenceString);

        return $hash . ':' . $encodedData;
    }

    /**
     * Create a hashed external reference for campaign donations.
     * Decoded format: "Donación Campaña ID: {id}; Slug: {slug}; Reward ID: {id}; User ID: {id}; Donation ID: {id}"
     */
    private function createHashedExternalReferenceForCampaignDonation(int $campaignId, string $slug, int $rewardId, $userId, int $donationId): string
    {
        $referenceString = sprintf(
            'Donación Campaña ID: %d; Slug: %s; Reward ID: %d; User ID: %s; Donation ID: %d',
            $campaignId,
            $slug,
            $rewardId,
            $userId,
            $donationId
        );

        return $this->buildHashedReference($referenceString);
    }

    /**
     * Create a hashed external reference for sellado de viaje.
     * Decoded format: "Sellado de Viaje ID: {tripId}" (same as legacy plain format for webhook compatibility).
     */
    private function createHashedExternalReferenceForSellado(int $tripId): string
    {
        $referenceString = 'Sellado de Viaje ID: ' . $tripId;

        return $this->buildHashedReference($referenceString);
    }

    /**
     * Create a payment preference for manual identity validation.
     *
     * @param int $requestId ManualIdentityValidation id
     * @param int|null $amountInCents Override from config if null
     * @param string|null $successRedirectUrl Optional override for success URL (default: backend manual-validation-success)
     */
    public function createPaymentPreferenceForManualValidation(int $requestId, ?int $amountInCents = null, ?string $successRedirectUrl = null): \MercadoPago\Resources\Preference
    {
        if ($amountInCents === null) {
            $amountInCents = config('carpoolear.manual_identity_validation_cost_cents', 0);
        }
        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('Manual identity validation cost must be positive');
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $successPath = $baseUrl . '/api/mercadopago/manual-validation-success?request_id=' . $requestId;
        $failurePath = $baseUrl . '/api/mercadopago/manual-validation-success?request_id=' . $requestId . '&result=failure';
        $pendingPath = $baseUrl . '/api/mercadopago/manual-validation-success?request_id=' . $requestId . '&result=pending';
        if ($successRedirectUrl !== null) {
            $successPath = $successRedirectUrl;
        }

        $urls = [
            'success' => $successPath,
            'failure' => $failurePath,
            'pending' => $pendingPath,
        ];

        // log urls
        \Log::info('MercadoPago URLS:', $urls);

        $preferenceData = [
            'items' => [
                [
                    'title' => 'Validación manual de identidad',
                    'quantity' => 1,
                    'unit_price' => floatval($amountInCents) / 100,
                    'currency_id' => 'ARS',
                ],
            ],
            'back_urls' => $urls,
            // API sometimes expects back_url (singular) when auto_return is set
            'back_url' => $urls,
            'auto_return' => 'approved',
            'external_reference' => 'manual_validation:' . $requestId,
        ];

        return $this->createPaymentPreference($preferenceData);
    }

    /**
     * Create a Mercado Pago QR order for manual identity validation.
     * Returns qr_data (string to display as QR), order_id, and payment_id.
     * Requires config carpoolear.qr_payment_pos_external_id (POS identifier from MP).
     *
     * Request we make:
     *   POST https://api.mercadopago.com/v1/orders
     *   Headers: Authorization: Bearer {token}, X-Idempotency-Key: {unique}, Content-Type: application/json
     *   Body: type=qr, total_amount, external_reference, config.qr.external_pos_id, config.qr.mode=dynamic,
     *         transactions.payments[].amount, items[], expiration_time (PT15M).
     *
     * Official docs:
     *   Create order (QR): https://www.mercadopago.com.ar/developers/en/reference/in-person-payments/qr-code/orders/create-order/post
     *   Search POS:        https://www.mercadopago.com.ar/developers/en/reference/pos/_pos/get
     *   Stores and POS:    https://www.mercadopago.com.ar/developers/en/docs/qr-code/stores-pos/stores-and-pos
     *
     * @param int $requestId ManualIdentityValidation id
     * @param int|null $amountInCents Override from config if null
     * @return array{request_id: int, order_id: string, qr_data: string, payment_id: string|null}
     */
    public function createQrOrderForManualValidation(int $requestId, ?int $amountInCents = null): array
    {
        $qrAccessToken = config('services.mercadopago.qr_payment_access_token', '');
        if ($qrAccessToken === '' || $qrAccessToken === null) {
            throw new \InvalidArgumentException('Mercado Pago QR payment access token is not configured (MERCADO_PAGO_QR_PAYMENT_ACCESS_TOKEN)');
        }
        $posExternalId = config('carpoolear.qr_payment_pos_external_id', '');
        if ($posExternalId === '' || $posExternalId === null) {
            throw new \InvalidArgumentException('QR POS external_id is not configured');
        }
        if ($amountInCents === null) {
            $amountInCents = config('carpoolear.manual_identity_validation_cost_cents', 0);
        }
        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('Manual identity validation cost must be positive');
        }
        // Mercado Pago QR Orders API minimum amount is 15.00 (see error "Amount must be greater than or equal to 15.00")
        $minAmountCents = 1500;
        if ($amountInCents < $minAmountCents) {
            throw new \InvalidArgumentException(
                'Mercado Pago QR orders require amount >= 15.00. Got ' . ($amountInCents / 100) . '. Set MANUAL_IDENTITY_VALIDATION_COST_CENTS >= 1500.'
            );
        }

        $amount = number_format(floatval($amountInCents) / 100, 2, '.', '');
        // Orders API allows only alphanumeric/underscore (max 64 chars); colon is rejected
        $externalReference = 'manual_validation_' . $requestId;

        $orderPayload = [
            'type' => 'qr',
            'total_amount' => $amount,
            'description' => 'Validación manual de identidad',
            'external_reference' => $externalReference,
            'expiration_time' => 'PT15M',
            'config' => [
                'qr' => [
                    'external_pos_id' => $posExternalId,
                    'mode' => 'dynamic',
                ],
            ],
            'transactions' => [
                'payments' => [
                    ['amount' => $amount],
                ],
            ],
            'items' => [
                [
                    'title' => 'Validación manual de identidad',
                    'unit_price' => $amount,
                    'quantity' => 1,
                    'unit_measure' => 'unit',
                ],
            ],
        ];

        $requestOptions = new RequestOptions();
        $requestOptions->setAccessToken($qrAccessToken);
        // SDK expects lowercase key: getIdempotencyKey() checks array_change_key_case($headers) but returns $headers[strtolower($key)]
        $requestOptions->setCustomHeaders([
            'x-idempotency-key' => 'manual_qr_' . $requestId . '_' . uniqid('', true),
        ]);

        \Log::info('MercadoPago QR Order request', [
            'request_id' => $requestId,
            'payload' => $orderPayload,
            'config_qr_external_pos_id' => $posExternalId,
        ]);

        try {
            $order = $this->orderClient->create($orderPayload, $requestOptions);
        } catch (MPApiException $e) {
            \Log::error('MercadoPago QR Order API Error:', [
                'message' => $e->getMessage(),
                'status' => $e->getApiResponse()->getStatusCode(),
                'response' => $e->getApiResponse()->getContent(),
                'request_payload' => $orderPayload,
                'config_qr_external_pos_id' => $posExternalId,
            ]);
            throw $e;
        }

        $paymentId = null;
        if (is_object($order->transactions) && isset($order->transactions->payments) && is_array($order->transactions->payments)) {
            $first = $order->transactions->payments[0] ?? null;
            if ($first && isset($first->id)) {
                $paymentId = $first->id;
            }
        }

        $qrData = '';
        $responseContent = $order->getResponse()->getContent();
        if ($responseContent !== null && $responseContent !== '') {
            $decoded = is_array($responseContent)
                ? $responseContent
                : json_decode($responseContent, true);
            if (is_array($decoded) && isset($decoded['type_response']['qr_data'])) {
                $qrData = (string) $decoded['type_response']['qr_data'];
            }
        }

        return [
            'request_id' => $requestId,
            'order_id' => $order->id ?? '',
            'qr_data' => $qrData,
            'payment_id' => $paymentId,
        ];
    }
}
