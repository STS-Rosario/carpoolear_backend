<?php

namespace Tests\Feature\Http;

use STS\Models\Trip;
use STS\Models\User;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class SelladoQrOrderApiTest extends TestCase
{
    private function enableSelladoQr(): void
    {
        $mercado = config('services.mercadopago', []);
        $mercado['qr_payment_access_token'] = 'qr-token';
        config([
            'services.mercadopago' => $mercado,
            'carpoolear.module_trip_creation_payment_enabled' => true,
            'carpoolear.module_trip_creation_payment_qr_enabled' => true,
            'carpoolear.module_trip_creation_payment_amount_cents' => 160000,
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);
    }

    private function pendingSelladoTrip(User $owner): Trip
    {
        return Trip::factory()->create([
            'user_id' => $owner->id,
            'is_passenger' => 0,
            'needs_sellado' => true,
            'state' => Trip::STATE_AWAITING_PAYMENT,
        ]);
    }

    public function test_owner_can_create_sellado_qr_order_when_qr_is_enabled(): void
    {
        $this->enableSelladoQr();
        $owner = User::factory()->create();
        $trip = $this->pendingSelladoTrip($owner);

        $this->mock(MercadoPagoService::class, function ($mock) use ($trip) {
            $mock->shouldReceive('createQrOrderForSellado')
                ->once()
                ->andReturn([
                    'trip_id' => $trip->id,
                    'order_id' => 'ord-sellado',
                    'qr_data' => 'SELLADO_QR',
                    'payment_id' => 'PAY-1',
                ]);
        });

        $this->actingAs($owner, 'api')
            ->postJson("/api/trips/{$trip->id}/sellado/qr-order")
            ->assertOk()
            ->assertJson([
                'trip_id' => $trip->id,
                'order_id' => 'ord-sellado',
                'qr_data' => 'SELLADO_QR',
            ]);
    }

    public function test_sellado_qr_order_is_unavailable_when_qr_flag_is_off(): void
    {
        $this->enableSelladoQr();
        config(['carpoolear.module_trip_creation_payment_qr_enabled' => false]);

        $owner = User::factory()->create();
        $trip = $this->pendingSelladoTrip($owner);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldNotReceive('createQrOrderForSellado');
        });

        $this->actingAs($owner, 'api')
            ->postJson("/api/trips/{$trip->id}/sellado/qr-order")
            ->assertStatus(503);
    }

    public function test_non_owner_cannot_create_sellado_qr_order(): void
    {
        $this->enableSelladoQr();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $trip = $this->pendingSelladoTrip($owner);

        $this->actingAs($other, 'api')
            ->postJson("/api/trips/{$trip->id}/sellado/qr-order")
            ->assertStatus(422);
    }
}
