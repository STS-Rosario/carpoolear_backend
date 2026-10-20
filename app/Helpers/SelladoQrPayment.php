<?php

namespace STS\Helpers;

class SelladoQrPayment
{
    public static function isEnabled(): bool
    {
        return (bool) config('carpoolear.module_trip_creation_payment_enabled', false)
            && (bool) config('carpoolear.module_trip_creation_payment_qr_enabled', false)
            && ! empty(config('services.mercadopago.qr_payment_access_token'))
            && ! empty(config('carpoolear.qr_payment_pos_external_id'));
    }
}
