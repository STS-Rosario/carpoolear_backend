<?php

namespace Tests\Unit\Services;

use InvalidArgumentException;
use STS\Models\Trip;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class MercadoPagoServiceTest extends TestCase
{
    public function test_create_payment_preference_throws_when_access_token_is_missing(): void
    {
        config(['services.mercadopago.access_token' => '']);

        $service = new MercadoPagoService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MercadoPago access token is not configured');

        $service->createPaymentPreference(['items' => []]);
    }

    public function test_create_payment_preference_for_sellado_throws_when_frontend_url_is_missing(): void
    {
        config(['carpoolear.frontend_url' => '']);

        $trip = Trip::factory()->create();
        $service = new MercadoPagoService;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carpoolear.frontend_url must be set for MercadoPago sellado');

        $service->createPaymentPreferenceForSellado($trip, 2000);
    }

    public function test_create_payment_preference_for_sellado_builds_back_urls_and_hashed_reference(): void
    {
        config([
            'carpoolear.frontend_url' => 'https://frontend.test',
            'services.mercadopago.reference_salt' => 'test-salt',
        ]);

        $trip = Trip::factory()->create();

        $service = new class extends MercadoPagoService
        {
            public array $capturedPayload = [];

            public function createPaymentPreference(array $preferenceData)
            {
                $this->capturedPayload = $preferenceData;

                return (object) ['ok' => true];
            }
        };

        $service->createPaymentPreferenceForSellado($trip, 2500);
        $payload = $service->capturedPayload;

        $expectedTripUrl = 'https://frontend.test/app/trips/'.$trip->id;
        $this->assertSame($expectedTripUrl, $payload['back_urls']['success']);
        $this->assertSame($expectedTripUrl, $payload['back_urls']['failure']);
        $this->assertSame($expectedTripUrl, $payload['back_urls']['pending']);
        $this->assertSame(25.0, $payload['items'][0]['unit_price']);
        $this->assertSame('approved', $payload['auto_return']);
        $this->assertIsString($payload['external_reference']);
        $this->assertStringContainsString(':', $payload['external_reference']);
    }

    public function test_create_qr_order_for_manual_validation_rejects_amount_below_provider_minimum(): void
    {
        config([
            'services.mercadopago.qr_payment_access_token' => 'token',
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);

        $service = new MercadoPagoService;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mercado Pago QR orders require amount >= 15.00');

        $service->createQrOrderForManualValidation(10, 1400);
    }
}
