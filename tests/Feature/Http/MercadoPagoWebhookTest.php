<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use STS\Models\ManualIdentityValidation;
use STS\Models\PaymentAttempt;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class MercadoPagoWebhookTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);
        parent::tearDown();
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
                $uri = $request->getUri();
                if (! preg_match('#/v1/payments/(\d+)#', $uri, $matches)) {
                    return new MPResponse(404, ['message' => 'unexpected uri']);
                }

                $id = (int) $matches[1];
                if (! array_key_exists($id, $this->paymentIdToPayload)) {
                    throw new \RuntimeException('Mercado Pago payment not found');
                }

                $payload = $this->paymentIdToPayload[$id];
                if (isset($payload['__simulate_fetch_error'])) {
                    throw new \Exception('Simulated Mercado Pago payment fetch failure');
                }

                return new MPResponse(200, $payload);
            }
        });
    }

    /**
     * Hashed format required when the decoded reference contains `:` (see `parseExternalReference`).
     */
    private function hashedSelladoExternalReference(int $tripId): string
    {
        $referenceString = 'Sellado de Viaje ID: '.$tripId;
        $salt = config('services.mercadopago.reference_salt', 'carpoolear_2024_secure_salt');
        $hash = hash('sha256', $referenceString.$salt);

        return $hash.':'.base64_encode($referenceString);
    }

    /**
     * @return array<string, mixed>
     */
    private function mercadoPagoPaymentPayload(string $externalReference, int $id): array
    {
        return [
            'id' => $id,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => 150.0,
            'currency_id' => 'ARS',
            'payment_method_id' => 'visa',
            'payment_type_id' => 'credit_card',
            'external_reference' => $externalReference,
            'description' => 'Test payment',
            'date_created' => '2026-01-01T00:00:00.000-00:00',
            'date_approved' => '2026-01-01T00:00:01.000-00:00',
            'date_last_updated' => '2026-01-01T00:00:02.000-00:00',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentCreatedSignatureHeaders(string $queryDataId, string $requestId, string $secret): array
    {
        $ts = (string) time();
        $manifest = "id:{$queryDataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        return [
            'x-request-id' => $requestId,
            'x-signature' => "ts={$ts},v1={$v1}",
        ];
    }

    /**
     * @return array<string, string>
     */
    private function orderProcessedSignatureHeaders(string $orderIdForQuery, string $requestId, string $secret): array
    {
        $dataId = strtolower($orderIdForQuery);
        $ts = (string) time();
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, $secret);

        return [
            'x-request-id' => $requestId,
            'x-signature' => "ts={$ts},v1={$v1}",
        ];
    }

    public function test_non_payment_webhook_actions_are_acknowledged_without_side_effects(): void
    {
        $this->postJson('/webhooks/mercadopago', [
            'action' => 'payment.updated',
        ])
            ->assertOk()
            ->assertExactJson(['status' => 'success']);
    }

    public function test_payment_created_without_verification_headers_is_rejected(): void
    {
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);

        $this->postJson('/webhooks/mercadopago?data_id=123', [
            'action' => 'payment.created',
            'data_id' => '123',
        ])
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid request']);
    }

    public function test_payment_created_with_invalid_signature_is_rejected(): void
    {
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);

        $this->postJson('/webhooks/mercadopago?data_id=123', [
            'action' => 'payment.created',
            'data_id' => '123',
        ], [
            'x-request-id' => 'req-1',
            'x-signature' => 'ts=1,v1=not-a-valid-hmac',
        ])
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid request']);
    }

    public function test_payment_created_when_provider_returns_no_payment_returns_server_error(): void
    {
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);

        $paymentId = 884422;
        $headers = $this->paymentCreatedSignatureHeaders((string) $paymentId, 'req-missing-payment', 'wh-secret-test');

        $this->stubMercadoPagoPayments([$paymentId => ['__simulate_fetch_error' => true]]);

        $this->postJson('/webhooks/mercadopago?data_id='.$paymentId, [
            'action' => 'payment.created',
            'data_id' => (string) $paymentId,
        ], $headers)
            ->assertStatus(500)
            ->assertJson(['error' => 'Could not fetch payment']);
    }

    public function test_payment_created_for_manual_validation_marks_request_paid_when_approved(): void
    {
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);

        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $paymentId = 77110055;
        $headers = $this->paymentCreatedSignatureHeaders((string) $paymentId, 'req-manual-ok', 'wh-secret-test');

        $this->stubMercadoPagoPayments([
            $paymentId => $this->mercadoPagoPaymentPayload('manual_validation:'.$row->id, $paymentId),
        ]);

        $this->postJson('/webhooks/mercadopago?data_id='.$paymentId, [
            'action' => 'payment.created',
            'data_id' => (string) $paymentId,
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $row->refresh();
        $this->assertTrue($row->paid);
        $this->assertNotNull($row->paid_at);
        $this->assertSame((string) $paymentId, $row->payment_id);
    }

    public function test_payment_created_with_unknown_external_reference_returns_client_error(): void
    {
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);

        $paymentId = 66110022;
        $headers = $this->paymentCreatedSignatureHeaders((string) $paymentId, 'req-unknown-ref', 'wh-secret-test');

        $this->stubMercadoPagoPayments([
            $paymentId => $this->mercadoPagoPaymentPayload('unclassified-reference', $paymentId),
        ]);

        $this->postJson('/webhooks/mercadopago?data_id='.$paymentId, [
            'action' => 'payment.created',
            'data_id' => (string) $paymentId,
        ], $headers)
            ->assertStatus(400)
            ->assertJson(['error' => 'Unknown payment type']);
    }

    public function test_order_processed_for_manual_validation_qr_prefix_marks_paid_when_accredited(): void
    {
        config(['services.mercadopago.webhook_secret_qr_payment' => 'wh-secret-qr-test']);

        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $orderId = 'ORD-UPPER-1';
        $headers = $this->orderProcessedSignatureHeaders($orderId, 'req-order-qr', 'wh-secret-qr-test');

        $this->postJson('/webhooks/mercadopago?'.http_build_query(['data.id' => $orderId]), [
            'action' => 'order.processed',
            'data' => [
                'external_reference' => 'manual_validation_'.$row->id,
                'status' => 'processed',
                'status_detail' => 'accredited',
                'transactions' => [
                    'payments' => [
                        ['id' => 'P-QR-99'],
                    ],
                ],
            ],
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $row->refresh();
        $this->assertTrue($row->paid);
        $this->assertSame('P-QR-99', $row->payment_id);
    }

    public function test_order_processed_before_accredited_does_not_mark_manual_validation_paid(): void
    {
        config(['services.mercadopago.webhook_secret_qr_payment' => 'wh-secret-qr-test']);

        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $orderId = 'ord-pending-2';
        $headers = $this->orderProcessedSignatureHeaders($orderId, 'req-order-pending', 'wh-secret-qr-test');

        $this->postJson('/webhooks/mercadopago?'.http_build_query(['data.id' => $orderId]), [
            'action' => 'order.processed',
            'data' => [
                'external_reference' => 'manual_validation_'.$row->id,
                'status' => 'processed',
                'status_detail' => 'pending_contingency',
            ],
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $this->assertFalse($row->fresh()->paid);
    }

    public function test_payment_created_for_trip_sellado_marks_payment_and_trip_ready_when_approved(): void
    {
        config(['services.mercadopago.webhook_secret' => 'wh-secret-test']);

        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'state' => Trip::STATE_PENDING_PAYMENT,
        ]);

        $paymentId = 55001133;
        $headers = $this->paymentCreatedSignatureHeaders((string) $paymentId, 'req-sellado', 'wh-secret-test');

        $external = $this->hashedSelladoExternalReference($trip->id);
        $this->stubMercadoPagoPayments([
            $paymentId => $this->mercadoPagoPaymentPayload($external, $paymentId),
        ]);

        $this->postJson('/webhooks/mercadopago?data_id='.$paymentId, [
            'action' => 'payment.created',
            'data_id' => (string) $paymentId,
        ], $headers)
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $this->assertDatabaseHas('payment_attempts', [
            'trip_id' => $trip->id,
            'payment_id' => $paymentId,
            'payment_status' => PaymentAttempt::STATUS_COMPLETED,
        ]);

        $this->assertSame(Trip::STATE_READY, $trip->fresh()->state);
    }
}
