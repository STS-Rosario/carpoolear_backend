<?php

namespace STS\Services;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Preference;
use MercadoPago\Item;

class MercadoPagoService
{
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token');
        MercadoPagoConfig::setAccessToken($this->accessToken);
    }

    public function createPaymentPreference($trip, $amountInCents)
    {
        $preference = new Preference();

        if (!amount) {
            $amount = config('carpoolear.module_trip_creation_payment_amount_cents');
        }

        $item = new Item();
        $item->title = "Sellado Carpoolear";
        $item->quantity = 1;
        $item->unit_price = $amountInCents / 100;
        $item->currency_id = "ARS";

        $preference->items = array($item);
        
        // Set up success and failure URLs
        $preference->back_urls = array(
            "success" => config('app.url') . "/app/trips/{$trip->id}/payment-success",
            "failure" => config('app.url') . "/app/trips/{$trip->id}/payment-failed",
            "pending" => config('app.url') . "/app/trips/{$trip->id}/payment-pending"
        );
        
        $preference->auto_return = "approved";
        $preference->save();

        return $preference;
    }
} 