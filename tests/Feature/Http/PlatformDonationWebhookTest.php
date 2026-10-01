<?php

namespace Tests\Feature\Http;

use Database\Seeders\DonationTierSeeder;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Models\DonationTier;
use STS\Models\User;
use Tests\TestCase;

class PlatformDonationWebhookTest extends TestCase
{
    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DonationTierSeeder::class);
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);
        config(['services.mercadopago.access_token' => 'test-access-token']);
        config(['services.mercadopago.reference_salt' => 'carpoolear_2024_secure_salt']);
    }

    private function hashedPlatformReference(int $recordId, string $type, int $userId, string $tierSlug): string
    {
        $referenceString = sprintf(
            'Donación Plataforma ID: %d; Tipo: %s; User ID: %d; Tier: %s',
            $recordId,
            $type,
            $userId,
            $tierSlug
        );
        $salt = config('services.mercadopago.reference_salt');
        $hash = hash('sha256', $referenceString.$salt);

        return $hash.':'.base64_encode($referenceString);
    }

    /**
     * @return array<string, string>
     */
    private function signatureHeaders(string $dataId, string $requestId, string $secret): array
    {
        $ts = (string) time();
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        return [
            'x-request-id' => $requestId,
            'x-signature' => "ts={$ts},v1={$v1}",
        ];
    }

    public function test_platform_payment_webhook_marks_donation_as_approved(): void
    {
        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $payment = DonationPayment::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'amount_cents' => 500000,
            'status' => 'pending',
        ]);

        $externalReference = $this->hashedPlatformReference($payment->id, 'once', $user->id, 'cafe');
        $mpPaymentId = 987654321;

        MercadoPagoConfig::setAccessToken('test-access-token');
        MercadoPagoConfig::setHttpClient(new class($mpPaymentId, $externalReference) implements MPHttpClient
        {
            public function __construct(private int $paymentId, private string $externalReference) {}

            public function send(MPRequest $request): MPResponse
            {
                return new MPResponse(200, [
                    'id' => $this->paymentId,
                    'status' => 'approved',
                    'status_detail' => 'accredited',
                    'transaction_amount' => 5000.0,
                    'currency_id' => 'ARS',
                    'payment_method_id' => 'visa',
                    'payment_type_id' => 'credit_card',
                    'external_reference' => $this->externalReference,
                    'description' => 'Donación',
                    'date_created' => '2026-08-24T12:00:00.000-00:00',
                    'date_approved' => '2026-08-24T12:00:00.000-00:00',
                    'date_last_updated' => '2026-08-24T12:00:00.000-00:00',
                ]);
            }
        });

        $headers = $this->signatureHeaders((string) $mpPaymentId, 'req-platform-1', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.$mpPaymentId, [
            'action' => 'payment.created',
            'data_id' => (string) $mpPaymentId,
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $payment->refresh();
        $this->assertSame('approved', $payment->status);
        $this->assertSame((string) $mpPaymentId, $payment->mp_payment_id);
    }

    public function test_subscription_preapproval_webhook_sets_monthly_donate(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        $tier = DonationTier::where('slug', 'beer')->firstOrFail();
        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'status' => 'pending',
            'transaction_amount_cents' => 750000,
            'external_reference' => $this->hashedPlatformReference(1, 'monthly', $user->id, 'beer'),
        ]);

        $preapprovalId = 'preapproval-test-1';
        $subscription->update(['mp_preapproval_id' => $preapprovalId]);

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($preapprovalId, $subscription) {
            $mock->shouldReceive('getPreapproval')
                ->once()
                ->with($preapprovalId)
                ->andReturn([
                    'id' => $preapprovalId,
                    'status' => 'authorized',
                    'external_reference' => $subscription->external_reference,
                    'auto_recurring' => ['transaction_amount' => 7500],
                    'next_payment_date' => '2026-09-24T00:00:00.000-00:00',
                ]);
        });

        $headers = $this->signatureHeaders($preapprovalId, 'req-preapproval-1', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.$preapprovalId, [
            'type' => 'subscription_preapproval',
            'action' => 'subscription_preapproval',
            'data_id' => $preapprovalId,
        ], $headers)
            ->assertOk();

        $subscription->refresh();
        $user->refresh();
        $this->assertSame('authorized', $subscription->status);
        $this->assertTrue($user->monthly_donate);
    }
}
