<?php

namespace Tests\Unit\Services;

use Database\Seeders\DonationTierSeeder;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Net\MPResponse;
use MercadoPago\Resources\Order;
use MercadoPago\Resources\Preference;
use ReflectionProperty;
use STS\Models\Campaign;
use STS\Models\DonationSubscription;
use STS\Models\DonationTier;
use STS\Models\Trip;
use STS\Models\User;
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

    public function test_create_qr_order_for_manual_validation_initializes_order_client_before_api_call(): void
    {
        config([
            'services.mercadopago.qr_payment_access_token' => 'qr-token',
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);

        $service = new MercadoPagoService;
        $orderClientRef = new ReflectionProperty(MercadoPagoService::class, 'orderClient');
        $orderClientRef->setAccessible(true);
        $this->assertNull($orderClientRef->getValue($service));

        try {
            $service->createQrOrderForManualValidation(42, 1500);
        } catch (\Error $e) {
            $this->fail('OrderClient was not initialized: '.$e->getMessage());
        } catch (\Throwable $e) {
            // Mercado Pago API errors are acceptable once OrderClient exists.
        }

        $this->assertNotNull($orderClientRef->getValue($service));
    }

    public function test_create_qr_order_for_manual_validation_returns_qr_data_from_order_client(): void
    {
        config([
            'services.mercadopago.qr_payment_access_token' => 'qr-token',
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);

        $service = new MercadoPagoService;
        $orderClientRef = new ReflectionProperty(MercadoPagoService::class, 'orderClient');
        $orderClientRef->setAccessible(true);
        $orderClientRef->setValue($service, new class
        {
            public function create(array $request, ?RequestOptions $requestOptions = null): Order
            {
                $order = new Order;
                $order->id = 'ORD-123';
                $order->transactions = (object) ['payments' => [(object) ['id' => 'PAY-456']]];
                $order->setResponse(new MPResponse(200, [
                    'type_response' => ['qr_data' => 'EMV_QR_DATA'],
                ]));

                return $order;
            }
        });

        $result = $service->createQrOrderForManualValidation(42, 1500);

        $this->assertSame(42, $result['request_id']);
        $this->assertSame('ORD-123', $result['order_id']);
        $this->assertSame('EMV_QR_DATA', $result['qr_data']);
        $this->assertSame('PAY-456', $result['payment_id']);
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

        $p = $service->capturedPayload;
        $this->assertSame('Validación manual de identidad', $p['items'][0]['title']);
        $this->assertSame(25.0, $p['items'][0]['unit_price']);
        $this->assertSame('manual_validation:77', $p['external_reference']);
    }

    public function test_create_payment_preference_for_manual_validation_uses_success_redirect_override(): void
    {
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

    public function test_create_preapproval_plan_sends_notification_url_and_welcome_back_url(): void
    {
        config([
            'app.url' => 'https://carpoolear.com.ar',
            'carpoolear.frontend_url' => 'https://carpoolear.com.ar',
            'services.mercadopago.access_token' => 'test-token',
        ]);

        $tier = new DonationTier([
            'slug' => 'cafe',
            'amount_cents' => 500000,
        ]);

        $service = new MercadoPagoService;
        $payload = $service->buildPreapprovalPlanRequest($tier);

        $this->assertSame(
            'https://carpoolear.com.ar/webhooks/mercadopago?source_news=webhooks',
            $payload['notification_url']
        );
        $this->assertSame(
            'https://carpoolear.com.ar/app/club-carpoolear/welcome?result=success',
            $payload['back_url']
        );
        $this->assertSame(5000.0, $payload['auto_recurring']['transaction_amount']);
        $this->assertSame('ARS', $payload['auto_recurring']['currency_id']);
        $this->assertSame(1, $payload['auto_recurring']['frequency']);
        $this->assertSame('months', $payload['auto_recurring']['frequency_type']);
    }

    public function test_create_preapproval_checkout_url_uses_plan_id_only_like_mercado_pago_init_point(): void
    {
        config(['services.mercadopago.access_token' => 'test-token']);
        $this->seed(DonationTierSeeder::class);

        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $planId = 'b1e39e90a1b84d759ff2a5a3cb636ac6';
        $tier->update(['mp_preapproval_plan_id' => $planId]);

        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_plan_id' => $planId,
            'status' => 'pending',
            'transaction_amount_cents' => 500000,
            'external_reference' => 'deadbeef:'.base64_encode('Donación Plataforma ID: 99'),
        ]);

        $service = new MercadoPagoService;
        $url = $service->createPreapprovalCheckoutUrl($subscription);

        $this->assertSame(
            'https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_plan_id='.$planId,
            $url
        );
        $this->assertStringNotContainsString('external_reference', $url);
        $this->assertNotNull($subscription->fresh()->external_reference);
    }

    public function test_get_authorized_payment_fetches_invoice_from_mercado_pago_api(): void
    {
        config(['services.mercadopago.access_token' => 'test-access-token']);

        Http::fake([
            'api.mercadopago.com/authorized_payments/7032479654' => Http::response([
                'id' => 7032479654,
                'preapproval_id' => 'preapproval-1',
                'payment' => ['id' => 181935207242, 'status' => 'approved'],
            ], 200),
        ]);

        $service = new MercadoPagoService;
        $invoice = $service->getAuthorizedPayment('7032479654');

        $this->assertIsArray($invoice);
        $this->assertSame(7032479654, $invoice['id']);
        $this->assertSame('preapproval-1', $invoice['preapproval_id']);
        $this->assertSame(181935207242, $invoice['payment']['id']);
    }
}
