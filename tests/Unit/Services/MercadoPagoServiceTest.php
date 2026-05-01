<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use MercadoPago\Resources\Preference;
use STS\Models\Campaign;
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

    public function test_sellado_trims_trailing_slash_on_frontend_url_for_back_urls(): void
    {
        config([
            'carpoolear.frontend_url' => 'https://frontend.test/',
            'services.mercadopago.reference_salt' => 'salt',
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

        $service->createPaymentPreferenceForSellado($trip, 1000);
        $success = $service->capturedPayload['back_urls']['success'];

        $this->assertSame('https://frontend.test/app/trips/'.$trip->id, $success);
        $this->assertStringNotContainsString('test//app', $success);
    }

    public function test_create_payment_preference_for_campaign_donation_builds_urls_title_and_items(): void
    {
        config([
            'app.url' => 'https://api.app.test',
            'services.mercadopago.reference_salt' => 'x-salt',
        ]);

        $campaign = Campaign::create([
            'slug' => 'spring-drive',
            'title' => 'Spring Campaign',
            'description' => 'Desc',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-slug',
        ]);

        $service = new class extends MercadoPagoService
        {
            public array $capturedPayload = [];

            public function createPaymentPreference(array $preferenceData)
            {
                $this->capturedPayload = $preferenceData;

                return (object) ['ok' => true];
            }
        };

        $service->createPaymentPreferenceForCampaignDonation($campaign->id, 8000, 5, 2, 9);

        $p = $service->capturedPayload;
        $base = 'https://api.app.test';

        $this->assertSame($base.'/campaigns/spring-drive?result=success', $p['back_urls']['success']);
        $this->assertSame($base.'/campaigns/spring-drive?result=failed', $p['back_urls']['failure']);
        $this->assertSame($base.'/campaigns/spring-drive?result=pending', $p['back_urls']['pending']);
        $this->assertSame('Donación para Carpoolear: Spring Campaign', $p['items'][0]['title']);
        $this->assertSame(1, $p['items'][0]['quantity']);
        $this->assertSame(80.0, $p['items'][0]['unit_price']);
        $this->assertSame('ARS', $p['items'][0]['currency_id']);
        $this->assertSame('approved', $p['auto_return']);
        $this->assertIsString($p['external_reference']);
    }

    public function test_create_payment_preference_for_manual_validation_logs_urls_and_builds_paths(): void
    {
        Log::spy();
        config([
            'app.url' => 'https://backend.test/',
            'carpoolear.manual_identity_validation_cost_cents' => 2000,
        ]);

        $service = new class extends MercadoPagoService
        {
            public array $capturedPayload = [];

            public function createPaymentPreference(array $preferenceData)
            {
                $this->capturedPayload = $preferenceData;

                return new Preference;
            }
        };

        $service->createPaymentPreferenceForManualValidation(77, 2500, null);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'MercadoPago URLS:'
                && ($context['success'] ?? '') === 'https://backend.test/api/mercadopago/manual-validation-success?request_id=77'
                && str_contains((string) ($context['failure'] ?? ''), 'request_id=77')
                && str_contains((string) ($context['pending'] ?? ''), 'result=pending');
        });

        $p = $service->capturedPayload;
        $this->assertSame('Validación manual de identidad', $p['items'][0]['title']);
        $this->assertSame(25.0, $p['items'][0]['unit_price']);
        $this->assertSame('manual_validation:77', $p['external_reference']);
    }

    public function test_create_payment_preference_for_manual_validation_uses_success_redirect_override(): void
    {
        Log::spy();
        config(['app.url' => 'https://backend.test']);

        $service = new class extends MercadoPagoService
        {
            public array $capturedPayload = [];

            public function createPaymentPreference(array $preferenceData)
            {
                $this->capturedPayload = $preferenceData;

                return new Preference;
            }
        };

        $service->createPaymentPreferenceForManualValidation(3, 1600, 'https://custom/success');

        $this->assertSame('https://custom/success', $service->capturedPayload['back_urls']['success']);
    }
}
