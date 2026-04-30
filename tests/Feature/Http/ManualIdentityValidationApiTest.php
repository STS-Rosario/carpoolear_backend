<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MercadoPago\Resources\Preference;
use Mockery;
use STS\Http\Controllers\Api\v1\ManualIdentityValidationController;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class ManualIdentityValidationApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
        ]);
    }

    public function test_constructor_registers_logged_middleware(): void
    {
        $controller = new ManualIdentityValidationController;
        $middlewares = $controller->getMiddleware();
        $logged = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedEmptyStatusPayload(): array
    {
        return [
            'has_submission' => false,
            'request_id' => null,
            'paid' => null,
            'paid_at' => null,
            'review_status' => null,
            'submitted_at' => null,
            'review_note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedPopulatedStatusPayload(ManualIdentityValidation $latest): array
    {
        $latest->refresh();

        return [
            'has_submission' => true,
            'request_id' => $latest->id,
            'paid' => $latest->paid,
            'paid_at' => $latest->paid_at?->toDateTimeString(),
            'review_status' => $latest->review_status,
            'submitted_at' => $latest->submitted_at?->toDateTimeString(),
            'review_note' => $latest->review_note,
        ];
    }

    public function test_manual_identity_cost_and_status_require_authentication(): void
    {
        $this->getJson('api/users/manual-identity-validation-cost')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);

        $this->getJson('api/users/manual-identity-validation')
            ->assertUnauthorized();
    }

    public function test_manual_identity_preference_and_qr_order_require_authentication(): void
    {
        $this->postJson('api/users/manual-identity-validation/preference')
            ->assertUnauthorized();

        $this->postJson('api/users/manual-identity-validation/qr-order')
            ->assertUnauthorized();
    }

    public function test_manual_identity_submit_requires_authentication(): void
    {
        $this->post('api/users/manual-identity-validation', [
            'request_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_cost_returns_zero_when_identity_validation_disabled(): void
    {
        config(['carpoolear.identity_validation_enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation-cost')
            ->assertOk()
            ->assertExactJson(['cost_cents' => 0]);
    }

    public function test_cost_returns_zero_when_manual_validation_disabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => false,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation-cost')
            ->assertOk()
            ->assertExactJson(['cost_cents' => 0]);
    }

    public function test_cost_returns_configured_amount_when_manual_flow_enabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 12_450,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation-cost')
            ->assertOk()
            ->assertExactJson(['cost_cents' => 12_450]);
    }

    public function test_status_returns_empty_contract_when_identity_validation_disabled(): void
    {
        config(['carpoolear.identity_validation_enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation')
            ->assertOk()
            ->assertExactJson($this->expectedEmptyStatusPayload());
    }

    public function test_status_returns_empty_contract_when_user_has_no_submission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation')
            ->assertOk()
            ->assertExactJson($this->expectedEmptyStatusPayload());
    }

    public function test_status_returns_latest_submission_summary(): void
    {
        $user = User::factory()->create();
        $paidAt = Carbon::parse('2026-05-01 10:30:00');
        $submittedAt = Carbon::parse('2026-05-02 15:00:00');

        $this->travelTo(Carbon::parse('2026-04-28 12:00:00'));
        ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => $paidAt->copy()->subDay(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'submitted_at' => $submittedAt->copy()->subDay(),
            'review_note' => 'older row',
        ]);

        $this->travelTo(Carbon::parse('2026-04-29 12:00:00'));
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => $paidAt,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'submitted_at' => $submittedAt,
            'review_note' => 'awaiting review',
        ]);
        $this->travelBack();

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation')
            ->assertOk()
            ->assertExactJson($this->expectedPopulatedStatusPayload($row));
    }

    public function test_status_serializes_null_timestamps_for_unpaid_submission(): void
    {
        $user = User::factory()->create();

        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'paid_at' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'submitted_at' => null,
            'review_note' => null,
        ]);

        $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation')
            ->assertOk()
            ->assertExactJson($this->expectedPopulatedStatusPayload($row));
    }

    public function test_preference_returns_unprocessable_when_identity_validation_disabled(): void
    {
        config(['carpoolear.identity_validation_enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Identity validation is not available.');
    }

    public function test_preference_returns_unprocessable_when_manual_validation_disabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => false,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Manual identity validation is not available.');
    }

    public function test_preference_returns_unprocessable_when_cost_not_positive(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 0,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Manual identity validation is not available.');
    }

    public function test_preference_returns_checkout_url_and_request_id(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        $user = User::factory()->create();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $preference = new Preference;
            $preference->init_point = 'https://checkout.example/pay';
            $preference->sandbox_init_point = null;

            $mock->shouldReceive('createPaymentPreferenceForManualValidation')
                ->once()
                ->andReturn($preference);
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference');

        $requestId = ManualIdentityValidation::where('user_id', $user->id)->value('id');

        $response->assertOk()
            ->assertExactJson([
                'init_point' => 'https://checkout.example/pay',
                'request_id' => $requestId,
            ]);

        $this->assertNotNull($requestId);
    }

    public function test_preference_reuses_existing_unpaid_request_instead_of_creating_a_second_row(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        $user = User::factory()->create();
        $existing = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) use ($existing) {
            $preference = new Preference;
            $preference->init_point = 'https://checkout.example/reuse';
            $preference->sandbox_init_point = null;

            $mock->shouldReceive('createPaymentPreferenceForManualValidation')
                ->once()
                ->withArgs(fn (int $requestId, ?int $amount, ?string $redirect): bool => $requestId === $existing->id
                    && $amount === 500
                    && $redirect === null)
                ->andReturn($preference);
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertOk()
            ->assertExactJson([
                'init_point' => 'https://checkout.example/reuse',
                'request_id' => $existing->id,
            ]);

        $this->assertSame(1, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    public function test_preference_falls_back_to_sandbox_checkout_url(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        $user = User::factory()->create();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $preference = new Preference;
            $preference->init_point = null;
            $preference->sandbox_init_point = 'https://sandbox.example/pay';

            $mock->shouldReceive('createPaymentPreferenceForManualValidation')
                ->once()
                ->andReturn($preference);
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference');

        $requestId = ManualIdentityValidation::where('user_id', $user->id)->value('id');

        $response->assertOk()
            ->assertExactJson([
                'init_point' => 'https://sandbox.example/pay',
                'request_id' => $requestId,
            ]);
    }

    public function test_preference_rolls_back_new_row_when_checkout_urls_missing(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        $user = User::factory()->create();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $preference = new Preference;
            $preference->init_point = null;
            $preference->sandbox_init_point = null;

            $mock->shouldReceive('createPaymentPreferenceForManualValidation')
                ->once()
                ->andReturn($preference);
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Failed to create payment preference.');

        $this->assertSame(0, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    public function test_preference_rolls_back_new_row_when_mercado_pago_throws(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        $user = User::factory()->create();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createPaymentPreferenceForManualValidation')
                ->once()
                ->andThrow(new \RuntimeException('Mercado Pago unavailable'));
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertStatus(500);

        $this->assertSame(0, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    public function test_preference_failure_keeps_existing_unpaid_request(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 500,
        ]);

        $user = User::factory()->create();
        $existing = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) use ($existing) {
            $mock->shouldReceive('createPaymentPreferenceForManualValidation')
                ->once()
                ->withArgs(fn (int $requestId): bool => $requestId === $existing->id)
                ->andThrow(new \RuntimeException('Mercado Pago unavailable'));
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/preference')
            ->assertStatus(500);

        $this->assertDatabaseHas('manual_identity_validations', ['id' => $existing->id]);
        $this->assertSame(1, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    public function test_qr_order_returns_unprocessable_when_qr_flow_disabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => false,
            'carpoolear.manual_identity_validation_cost_cents' => 2_000,
            'carpoolear.qr_payment_pos_external_id' => 'POS_EXT',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'QR payment is not available.');
    }

    public function test_qr_order_returns_unprocessable_when_pos_external_id_missing(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 2_000,
            'carpoolear.qr_payment_pos_external_id' => '',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'QR payment is not available.');
    }

    public function test_qr_order_returns_unprocessable_when_cost_not_positive(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 0,
            'carpoolear.qr_payment_pos_external_id' => 'POS_EXT',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Manual identity validation is not available.');
    }

    public function test_qr_order_returns_payload_from_payment_provider(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 2_000,
            'carpoolear.qr_payment_pos_external_id' => 'POS_EXT',
        ]);

        $user = User::factory()->create();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createQrOrderForManualValidation')
                ->once()
                ->andReturnUsing(function (int $requestId, ?int $amountInCents) {
                    return [
                        'request_id' => $requestId,
                        'order_id' => 'mp-order-abc',
                        'qr_data' => 'encoded-qr-payload',
                        'payment_id' => null,
                    ];
                });
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order');

        $requestId = ManualIdentityValidation::where('user_id', $user->id)->value('id');

        $response->assertOk()
            ->assertExactJson([
                'request_id' => $requestId,
                'qr_data' => 'encoded-qr-payload',
                'order_id' => 'mp-order-abc',
            ]);
    }

    public function test_qr_order_reuses_existing_unpaid_request_instead_of_creating_a_second_row(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 2_000,
            'carpoolear.qr_payment_pos_external_id' => 'POS_EXT',
        ]);

        $user = User::factory()->create();
        $existing = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) use ($existing) {
            $mock->shouldReceive('createQrOrderForManualValidation')
                ->once()
                ->withArgs(fn (int $requestId, ?int $amount): bool => $requestId === $existing->id && $amount === 2_000)
                ->andReturn([
                    'request_id' => $existing->id,
                    'order_id' => 'mp-order-reuse',
                    'qr_data' => 'qr-reuse-payload',
                    'payment_id' => null,
                ]);
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order')
            ->assertOk()
            ->assertExactJson([
                'request_id' => $existing->id,
                'qr_data' => 'qr-reuse-payload',
                'order_id' => 'mp-order-reuse',
            ]);

        $this->assertSame(1, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    public function test_qr_order_rolls_back_new_row_when_qr_data_missing(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 2_000,
            'carpoolear.qr_payment_pos_external_id' => 'POS_EXT',
        ]);

        $user = User::factory()->create();

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createQrOrderForManualValidation')
                ->once()
                ->andReturnUsing(function (int $requestId) {
                    return [
                        'request_id' => $requestId,
                        'order_id' => 'mp-order-empty',
                        'qr_data' => '',
                        'payment_id' => null,
                    ];
                });
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Failed to create QR order.');

        $this->assertSame(0, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    public function test_qr_order_failure_keeps_existing_unpaid_request(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
            'carpoolear.identity_validation_manual_qr_enabled' => true,
            'carpoolear.manual_identity_validation_cost_cents' => 2_000,
            'carpoolear.qr_payment_pos_external_id' => 'POS_EXT',
        ]);

        $user = User::factory()->create();
        $existing = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) use ($existing) {
            $mock->shouldReceive('createQrOrderForManualValidation')
                ->once()
                ->withArgs(fn (int $requestId): bool => $requestId === $existing->id)
                ->andThrow(new \RuntimeException('Mercado Pago unavailable'));
        });

        $this->actingAs($user, 'api')
            ->postJson('api/users/manual-identity-validation/qr-order')
            ->assertStatus(500);

        $this->assertDatabaseHas('manual_identity_validations', ['id' => $existing->id]);
        $this->assertSame(1, ManualIdentityValidation::where('user_id', $user->id)->count());
    }

    protected function createPaidValidationRequest(User $user): ManualIdentityValidation
    {
        return ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);
    }

    public function test_submit_without_request_id_returns_unprocessable(): void
    {
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);
        $front = UploadedFile::fake()->image('front.jpg', 100, 100)->size(500);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'request_id is required.');

        $this->assertNull($validationRequest->fresh()->submitted_at);
    }

    public function test_submit_with_unknown_request_id_returns_unprocessable_invalid_request(): void
    {
        $user = User::factory()->create();
        $this->createPaidValidationRequest($user);
        $missingId = (int) (ManualIdentityValidation::query()->max('id') ?? 0) + 99_999;

        $front = UploadedFile::fake()->image('front.jpg', 100, 100)->size(500);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $missingId,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid request.');
    }

    public function test_submit_with_request_id_owned_by_another_user_returns_unprocessable_invalid_request(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $otherRequest = $this->createPaidValidationRequest($owner);

        $front = UploadedFile::fake()->image('front.jpg', 100, 100)->size(500);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $this->actingAs($other, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $otherRequest->id,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid request.');

        $this->assertNull($otherRequest->fresh()->submitted_at);
    }

    public function test_submit_when_not_paid_returns_unprocessable(): void
    {
        $user = User::factory()->create();
        $validationRequest = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $front = UploadedFile::fake()->image('front.jpg', 100, 100)->size(500);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $validationRequest->id,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment is required before submitting images.');

        $this->assertNull($validationRequest->fresh()->submitted_at);
    }

    public function test_submit_with_missing_selfie_returns_unprocessable(): void
    {
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);
        $front = UploadedFile::fake()->image('front.jpg', 100, 100)->size(500);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);

        $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $validationRequest->id,
            'front_image' => $front,
            'back_image' => $back,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'All three images are required: front_image, back_image, selfie_image.');

        $this->assertNull($validationRequest->fresh()->submitted_at);
    }

    public function test_submit_with_valid_jpeg_returns_201_and_saves_paths(): void
    {
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);

        $front = UploadedFile::fake()->image('front.jpg', 100, 100)->size(500);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $response = $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $validationRequest->id,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ]);

        $response->assertStatus(201);
        $validationRequest->refresh();
        $this->assertNotNull($validationRequest->front_image_path);
        $this->assertNotNull($validationRequest->back_image_path);
        $this->assertNotNull($validationRequest->selfie_image_path);
        $this->assertNotNull($validationRequest->submitted_at);
    }

    public function test_submit_with_disallowed_file_type_returns_422(): void
    {
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);

        $front = UploadedFile::fake()->create('front.exe', 100, 'application/octet-stream');
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $response = $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $validationRequest->id,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ]);

        $response->assertStatus(422);
        $validationRequest->refresh();
        $this->assertNull($validationRequest->front_image_path);
        $this->assertNull($validationRequest->submitted_at);
    }

    public function test_submit_with_file_over_size_returns_422(): void
    {
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);

        $front = UploadedFile::fake()->image('front.jpg', 1000, 1000)->size(1024 * 11);
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $response = $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $validationRequest->id,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ]);

        $response->assertStatus(422);
        $validationRequest->refresh();
        $this->assertNull($validationRequest->front_image_path);
    }

    public function test_submit_with_heic_stores_as_jpeg(): void
    {
        if (! \STS\Services\HeicToJpegConverter::isAvailable()) {
            $this->markTestSkipped('HEIC to JPEG conversion is not available (Imagick with HEIC support required)');
        }
        config(['carpoolear.image_upload_convert_heic_to_jpeg' => true]);
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);

        $front = \STS\Services\HeicToJpegConverter::createValidHeicFile();
        $back = UploadedFile::fake()->image('back.jpg', 100, 100)->size(500);
        $selfie = UploadedFile::fake()->image('selfie.jpg', 100, 100)->size(500);

        $response = $this->actingAs($user, 'api')->post('api/users/manual-identity-validation', [
            'request_id' => $validationRequest->id,
            'front_image' => $front,
            'back_image' => $back,
            'selfie_image' => $selfie,
        ]);

        $response->assertStatus(201);
        $validationRequest->refresh();
        $this->assertNotNull($validationRequest->front_image_path);
        $this->assertStringEndsWith('.jpg', $validationRequest->front_image_path);

        @unlink($front->getRealPath());
    }
}
