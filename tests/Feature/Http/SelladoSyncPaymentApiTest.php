<?php

namespace Tests\Feature\Http;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use STS\Models\PaymentAttempt;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class SelladoSyncPaymentApiTest extends TestCase
{
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

                return new MPResponse(200, $this->paymentIdToPayload[$id]);
            }
        });
    }

    private function hashedSelladoExternalReference(int $tripId): string
    {
        $referenceString = 'Sellado de Viaje ID: '.$tripId;
        $salt = config('services.mercadopago.reference_salt', 'carpoolear_2024_secure_salt');
        $hash = hash('sha256', $referenceString.$salt);

        return $hash.':'.base64_encode($referenceString);
    }

    public function test_owner_can_sync_approved_sellado_payment_from_return_url(): void
    {
        config(['services.mercadopago.access_token' => 'test-access-token']);

        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $owner->id,
            'is_passenger' => 0,
            'needs_sellado' => true,
            'state' => Trip::STATE_PENDING_PAYMENT,
        ]);

        $paymentId = 184993400950;
        $this->stubMercadoPagoPayments([
            $paymentId => [
                'id' => $paymentId,
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 1600.0,
                'currency_id' => 'ARS',
                'payment_method_id' => 'account_money',
                'payment_type_id' => 'account_money',
                'external_reference' => $this->hashedSelladoExternalReference($trip->id),
                'date_created' => '2026-10-19T12:14:00.000-03:00',
                'date_approved' => '2026-10-19T12:14:01.000-03:00',
                'date_last_updated' => '2026-10-19T12:14:01.000-03:00',
            ],
        ]);

        $this->actingAs($owner, 'api')
            ->postJson("/api/trips/{$trip->id}/sellado/sync-payment", [
                'payment_id' => (string) $paymentId,
            ])
            ->assertOk();

        $this->assertDatabaseHas('payment_attempts', [
            'trip_id' => $trip->id,
            'payment_id' => $paymentId,
            'payment_status' => PaymentAttempt::STATUS_COMPLETED,
        ]);
        $this->assertSame(Trip::STATE_READY, $trip->fresh()->state);
    }
}
