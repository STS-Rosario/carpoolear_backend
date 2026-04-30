<?php

namespace Tests\Feature\Http;

use STS\Contracts\WebpayNormalFlowClient;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\Support\Webpay\FakeWebpayNormalFlowClient;
use Tests\TestCase;

class PaymentControllerWebTest extends TestCase
{
    private FakeWebpayNormalFlowClient $webpay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webpay = new FakeWebpayNormalFlowClient;
        $this->app->instance(WebpayNormalFlowClient::class, $this->webpay);
    }

    public function test_transbank_without_tp_id_returns_plain_text_message(): void
    {
        $response = $this->get('/transbank');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('content-type'));
        $this->assertSame('No transaction id', $response->getContent());
    }

    public function test_transbank_with_unknown_tp_id_does_not_start_checkout(): void
    {
        $response = $this->get('/transbank?tp_id=999999999');

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        $this->assertNull($this->webpay->lastInit);
    }

    public function test_transbank_with_passenger_passes_amount_and_callback_urls_to_webpay(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'seat_price_cents' => 3500,
        ]);
        $passengerUser = User::factory()->create();
        $passenger = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $response = $this->get('/transbank?tp_id='.$passenger->id);

        $response->assertOk();
        $response->assertSee('https://webpay.test-wsp/redirect', false);
        $response->assertSee('name="token_ws"', false);
        $response->assertSee('value="unit-test-token"', false);

        $this->assertNotNull($this->webpay->lastInit);
        $this->assertSame(3500, $this->webpay->lastInit->amount);
        $this->assertSame((string) $passenger->id, $this->webpay->lastInit->buyOrder);
        $this->assertStringEndsWith('/transbank-respuesta', $this->webpay->lastInit->returnUrl);
        $this->assertStringEndsWith('/transbank-final', $this->webpay->lastInit->finalUrl);
    }

    public function test_transbank_response_marks_passenger_paid_on_approved_code(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id, 'seat_price_cents' => 1000]);
        $passengerUser = User::factory()->create();
        $passenger = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $detail = new \stdClass;
        $detail->responseCode = 0;
        $payload = new \stdClass;
        $payload->buyOrder = (string) $passenger->id;
        $payload->detailOutput = $detail;
        $payload->urlRedirection = 'https://webpay.test-wsp/voucher';
        $this->webpay->transactionResult = $payload;

        $this->post('/transbank-respuesta', ['token_ws' => 'tbk-token-1'])
            ->assertOk()
            ->assertViewIs('transbank')
            ->assertViewHas('formAction', 'https://webpay.test-wsp/voucher')
            ->assertViewHas('tokenWs', 'tbk-token-1');

        $passenger->refresh();
        $this->assertSame(Passenger::STATE_ACCEPTED, (int) $passenger->request_state);
        $this->assertSame('ok', $passenger->payment_status);
        $this->assertNotNull($passenger->payment_info);
    }

    public function test_transbank_response_records_declined_payment_status(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        $passengerUser = User::factory()->create();
        $passenger = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
        ]);

        $detail = new \stdClass;
        $detail->responseCode = -1;
        $payload = new \stdClass;
        $payload->buyOrder = (string) $passenger->id;
        $payload->detailOutput = $detail;
        $this->webpay->transactionResult = $payload;

        $this->post('/transbank-respuesta', ['token_ws' => 'tbk-token-2'])
            ->assertOk()
            ->assertViewIs('transbank-final')
            ->assertViewHas('message', 'Ocurrió un error al procesar la operación.');

        $passenger->refresh();
        $this->assertStringStartsWith('error:-1:', (string) $passenger->payment_status);
        $this->assertStringContainsString('Rechazo de transacción', (string) $passenger->payment_status);
    }

    public function test_transbank_response_shows_not_found_when_passenger_missing(): void
    {
        $detail = new \stdClass;
        $detail->responseCode = 0;
        $payload = new \stdClass;
        $payload->buyOrder = '42424242';
        $payload->detailOutput = $detail;
        $this->webpay->transactionResult = $payload;

        $this->post('/transbank-respuesta', ['token_ws' => 'x'])
            ->assertOk()
            ->assertViewIs('transbank-final')
            ->assertViewHas('message', 'Operación no encontrada');
    }

    public function test_transbank_response_shows_empty_output_when_gateway_returns_null(): void
    {
        $this->webpay->transactionResult = null;

        $this->post('/transbank-respuesta', ['token_ws' => ''])
            ->assertOk()
            ->assertViewIs('transbank-final')
            ->assertViewHas('message', 'Transbank ouput empty.');
    }

    public function test_transbank_final_renders_success_page(): void
    {
        $this->get('/transbank-final')
            ->assertOk()
            ->assertViewIs('transbank-final')
            ->assertViewHas('message', 'Transacción realizada con éxito.');
    }
}
