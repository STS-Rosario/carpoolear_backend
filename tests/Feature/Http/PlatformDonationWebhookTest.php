<?php

namespace Tests\Feature\Http;

use Database\Seeders\DonationTierSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
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

    /**
     * @param  array<string, string>  $query
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function postMercadoPagoWebhookPreservingDottedDataIdQuery(
        string $dataId,
        array $query,
        array $payload,
        array $headers
    ): \Symfony\Component\HttpFoundation\Response {
        $request = Request::create(
            '/webhooks/mercadopago',
            'POST',
            $payload,
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => $headers['x-request-id'],
                'HTTP_X_SIGNATURE' => $headers['x-signature'],
            ]
        );
        $request->headers->set('x-request-id', $headers['x-request-id']);
        $request->headers->set('x-signature', $headers['x-signature']);
        $request->query->replace(array_merge($query, ['data.id' => $dataId]));

        return $this->app->make(Kernel::class)->handle($request);
    }

    /**
     * @param  array<int, array<string, mixed>>  $paymentIdToPayload
     */
    private function stubMercadoPagoPayments(array $paymentIdToPayload): void
    {
        MercadoPagoConfig::setAccessToken('test-access-token');
        MercadoPagoConfig::setHttpClient(new class($paymentIdToPayload) implements MPHttpClient
        {
            public function __construct(private array $paymentIdToPayload) {}

            public function send(MPRequest $request): MPResponse
            {
                if (! preg_match('#/v1/payments/(\d+)#', $request->getUri(), $matches)) {
                    return new MPResponse(404, ['message' => 'unexpected uri']);
                }

                $id = (int) $matches[1];
                if (! array_key_exists($id, $this->paymentIdToPayload)) {
                    throw new \RuntimeException('Mercado Pago payment not found');
                }

                return new MPResponse(200, $this->paymentIdToPayload[$id]);
            }
        });
    }

    private function authorizedClubSubscription(User $user, string $preapprovalId): DonationSubscription
    {
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();

        return DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_id' => $preapprovalId,
            'mp_preapproval_plan_id' => 'b1e39e90a1b84d759ff2a5a3cb636ac6',
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
        ]);
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
        $this->assertNotNull($user->club_carpoolear_joined_at);
    }

    public function test_subscription_preapproval_webhook_accepts_production_data_id_and_action_created(): void
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

        $preapprovalId = 'preapproval-prod-data-id';
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

        $headers = $this->signatureHeaders($preapprovalId, 'req-preapproval-data-id', 'wh-secret-test');

        $response = $this->postMercadoPagoWebhookPreservingDottedDataIdQuery(
            $preapprovalId,
            ['type' => 'subscription_preapproval'],
            [
                'type' => 'subscription_preapproval',
                'action' => 'created',
                'data' => ['id' => $preapprovalId],
            ],
            $headers
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame(['status' => 'success'], json_decode($response->getContent(), true));

        $subscription->refresh();
        $user->refresh();
        $this->assertSame('authorized', $subscription->status);
        $this->assertTrue($user->monthly_donate);
        $this->assertNotNull($user->club_carpoolear_joined_at);
    }

    public function test_subscription_preapproval_webhook_links_pending_checkout_when_mp_has_no_external_reference(): void
    {
        $user = User::factory()->create([
            'email' => 'club-member@example.test',
            'monthly_donate' => false,
            'club_carpoolear_joined_at' => null,
        ]);
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $planId = 'plan-cafe-shared';
        $tier->update(['mp_preapproval_plan_id' => $planId]);

        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_plan_id' => $planId,
            'status' => 'pending',
            'transaction_amount_cents' => 500000,
            'external_reference' => $this->hashedPlatformReference(42, 'monthly', $user->id, 'cafe'),
        ]);

        $preapprovalId = 'preapproval-from-mp-hosted-checkout';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($preapprovalId, $planId, $user) {
            $mock->shouldReceive('getPreapproval')
                ->once()
                ->with($preapprovalId)
                ->andReturn([
                    'id' => $preapprovalId,
                    'status' => 'authorized',
                    'preapproval_plan_id' => $planId,
                    'payer_email' => $user->email,
                    'auto_recurring' => ['transaction_amount' => 5000],
                    'next_payment_date' => '2026-11-01T00:00:00.000-00:00',
                ]);
        });

        $headers = $this->signatureHeaders($preapprovalId, 'req-pending-no-mp-ref', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.urlencode($preapprovalId), [
            'type' => 'subscription_preapproval',
            'action' => 'created',
            'data_id' => $preapprovalId,
        ], $headers)
            ->assertOk();

        $this->assertSame(1, DonationSubscription::query()->count());
        $subscription->refresh();
        $user->refresh();
        $this->assertSame($preapprovalId, $subscription->mp_preapproval_id);
        $this->assertSame('authorized', $subscription->status);
        $this->assertTrue($user->monthly_donate);
        $this->assertNotNull($user->club_carpoolear_joined_at);
    }

    public function test_subscription_preapproval_webhook_creates_row_when_none_exists(): void
    {
        $user = User::factory()->create([
            'monthly_donate' => false,
            'club_carpoolear_joined_at' => null,
        ]);
        $this->assertSame(0, DonationSubscription::query()->count());

        $preapprovalId = 'preapproval-orphan-1';
        $externalReference = $this->hashedPlatformReference(999999, 'monthly', $user->id, 'cafe');

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($preapprovalId, $externalReference) {
            $mock->shouldReceive('getPreapproval')
                ->once()
                ->with($preapprovalId)
                ->andReturn([
                    'id' => $preapprovalId,
                    'status' => 'authorized',
                    'external_reference' => $externalReference,
                    'preapproval_plan_id' => 'plan-cafe',
                    'auto_recurring' => ['transaction_amount' => 5000],
                    'next_payment_date' => '2026-11-01T00:00:00.000-00:00',
                ]);
        });

        $headers = $this->signatureHeaders($preapprovalId, 'req-orphan-preapproval', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.urlencode($preapprovalId), [
            'type' => 'subscription_preapproval',
            'action' => 'created',
            'data_id' => $preapprovalId,
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $subscription = DonationSubscription::query()->where('mp_preapproval_id', $preapprovalId)->first();
        $this->assertNotNull($subscription);
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame('authorized', $subscription->status);
        $this->assertSame(500000, $subscription->transaction_amount_cents);
        $this->assertSame($externalReference, $subscription->external_reference);

        $user->refresh();
        $this->assertTrue((bool) $user->monthly_donate);
        $this->assertNotNull($user->club_carpoolear_joined_at);
    }

    public function test_subscription_preapproval_webhook_logs_warning_when_user_cannot_be_resolved(): void
    {
        Log::spy();

        $preapprovalId = 'preapproval-no-user';
        $externalReference = $this->hashedPlatformReference(888888, 'monthly', 42424242, 'cafe');

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($preapprovalId, $externalReference) {
            $mock->shouldReceive('getPreapproval')
                ->once()
                ->with($preapprovalId)
                ->andReturn([
                    'id' => $preapprovalId,
                    'status' => 'authorized',
                    'external_reference' => $externalReference,
                    'auto_recurring' => ['transaction_amount' => 5000],
                ]);
        });

        $headers = $this->signatureHeaders($preapprovalId, 'req-preapproval-no-user', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.urlencode($preapprovalId), [
            'type' => 'subscription_preapproval',
            'action' => 'updated',
            'data_id' => $preapprovalId,
        ], $headers)
            ->assertOk();

        $this->assertTrue(
            DonationSubscription::query()->where('mp_preapproval_id', $preapprovalId)->exists()
        );
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'could not resolve user')
                && ($context['preapproval_id'] ?? null) === 'preapproval-no-user';
        });
    }

    public function test_subscription_authorized_payment_webhook_upserts_charge_from_invoice(): void
    {
        $user = User::factory()->create();
        $preapprovalId = 'a509fda0d6e543d38b79657e028bfbe4';
        $subscription = $this->authorizedClubSubscription($user, $preapprovalId);

        $invoiceId = '7032479654';
        $nestedPaymentId = 181935207242;

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($invoiceId, $preapprovalId, $nestedPaymentId) {
            $mock->shouldReceive('getAuthorizedPayment')
                ->once()
                ->with($invoiceId)
                ->andReturn([
                    'id' => (int) $invoiceId,
                    'preapproval_id' => $preapprovalId,
                    'transaction_amount' => 5000,
                    'status' => 'processed',
                    'payment' => [
                        'id' => $nestedPaymentId,
                        'status' => 'approved',
                        'status_detail' => 'accredited',
                        'transaction_amount' => 5000,
                        'date_approved' => '2026-10-02T02:36:39.000-00:00',
                    ],
                ]);
            $mock->shouldReceive('getPayment')->never();
        });

        $headers = $this->signatureHeaders($invoiceId, 'req-invoice-charge', 'wh-secret-test');

        $response = $this->postMercadoPagoWebhookPreservingDottedDataIdQuery(
            $invoiceId,
            ['type' => 'subscription_authorized_payment'],
            [
                'type' => 'subscription_authorized_payment',
                'action' => 'updated',
            ],
            $headers
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('donation_subscription_charges', [
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => (string) $nestedPaymentId,
            'status' => 'approved',
            'amount_cents' => 500000,
        ]);
        $this->assertNotNull($subscription->fresh()->last_charged_at);
    }

    public function test_subscription_authorized_payment_without_nested_payment_acknowledges_without_charge(): void
    {
        $user = User::factory()->create();
        $preapprovalId = 'preapproval-pending-invoice';
        $this->authorizedClubSubscription($user, $preapprovalId);

        $invoiceId = '7032479999';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($invoiceId, $preapprovalId) {
            $mock->shouldReceive('getAuthorizedPayment')
                ->once()
                ->with($invoiceId)
                ->andReturn([
                    'id' => (int) $invoiceId,
                    'preapproval_id' => $preapprovalId,
                    'status' => 'scheduled',
                    'transaction_amount' => 5000,
                ]);
        });

        $headers = $this->signatureHeaders($invoiceId, 'req-invoice-no-payment', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?'.http_build_query([
            'data.id' => $invoiceId,
            'type' => 'subscription_authorized_payment',
        ]), [
            'type' => 'subscription_authorized_payment',
            'action' => 'updated',
        ], $headers)
            ->assertOk();

        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
    }

    public function test_subscription_authorized_payment_logs_warning_when_subscription_not_found(): void
    {
        Log::spy();

        $invoiceId = '7032480001';
        $nestedPaymentId = 18193529999;

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($invoiceId, $nestedPaymentId) {
            $mock->shouldReceive('getAuthorizedPayment')
                ->once()
                ->with($invoiceId)
                ->andReturn([
                    'id' => (int) $invoiceId,
                    'preapproval_id' => 'unknown-preapproval',
                    'payment' => [
                        'id' => $nestedPaymentId,
                        'status' => 'approved',
                        'transaction_amount' => 5000,
                    ],
                ]);
        });

        $headers = $this->signatureHeaders($invoiceId, 'req-invoice-no-sub', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.$invoiceId, [
            'type' => 'subscription_authorized_payment',
            'action' => 'updated',
        ], $headers)
            ->assertOk();

        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
        Log::shouldHaveReceived('warning')->withArgs(function (string $message): bool {
            return str_contains($message, 'subscription charge webhook could not match');
        });
    }

    public function test_payment_created_with_empty_reference_and_preapproval_id_upserts_club_charge(): void
    {
        $user = User::factory()->create();
        $preapprovalId = 'a509fda0d6e543d38b79657e028bfbe4';
        $subscription = $this->authorizedClubSubscription($user, $preapprovalId);

        $paymentId = 181935207242;
        $this->stubMercadoPagoPayments([
            $paymentId => [
                'id' => $paymentId,
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 5000.0,
                'external_reference' => '',
                'preapproval_id' => $preapprovalId,
                'date_approved' => '2026-10-02T02:36:39.000-00:00',
            ],
        ]);

        $headers = $this->signatureHeaders((string) $paymentId, 'req-club-payment-empty-ref', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?'.http_build_query([
            'data.id' => (string) $paymentId,
            'type' => 'payment',
        ]), [
            'action' => 'payment.created',
            'type' => 'payment',
        ], $headers)
            ->assertOk();

        $this->assertDatabaseHas('donation_subscription_charges', [
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => (string) $paymentId,
            'status' => 'approved',
        ]);
    }

    public function test_payment_created_with_empty_reference_and_no_preapproval_is_acknowledged(): void
    {
        Log::spy();

        $paymentId = 18193530001;
        $this->stubMercadoPagoPayments([
            $paymentId => [
                'id' => $paymentId,
                'status' => 'approved',
                'transaction_amount' => 10.0,
                'external_reference' => '',
            ],
        ]);

        $headers = $this->signatureHeaders((string) $paymentId, 'req-unreferenced-payment', 'wh-secret-test');

        $this->postJson('/webhooks/mercadopago?data_id='.$paymentId, [
            'action' => 'payment.created',
            'data_id' => (string) $paymentId,
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
        Log::shouldHaveReceived('info')->withArgs(function (string $message): bool {
            return str_contains($message, 'Unreferenced MercadoPago payment ignored');
        });
    }
}
