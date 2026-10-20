<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\SelladoQrPayment;
use Tests\TestCase;

class SelladoQrPaymentTest extends TestCase
{
    public function test_is_enabled_requires_sellado_module_qr_flag_and_credentials(): void
    {
        $mercado = config('services.mercadopago', []);
        $mercado['qr_payment_access_token'] = 'qr-token';
        config([
            'services.mercadopago' => $mercado,
            'carpoolear.module_trip_creation_payment_enabled' => true,
            'carpoolear.module_trip_creation_payment_qr_enabled' => true,
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);

        $this->assertTrue(SelladoQrPayment::isEnabled());

        config(['carpoolear.module_trip_creation_payment_qr_enabled' => false]);
        $this->assertFalse(SelladoQrPayment::isEnabled());

        config([
            'carpoolear.module_trip_creation_payment_qr_enabled' => true,
            'carpoolear.module_trip_creation_payment_enabled' => false,
        ]);
        $this->assertFalse(SelladoQrPayment::isEnabled());
    }
}
