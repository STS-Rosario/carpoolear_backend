<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use Tests\TestCase;

class ManualIdentityValidationApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_manual_enabled' => true,
        ]);
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

    public function test_manual_identity_cost_and_status_require_authentication(): void
    {
        $this->getJson('api/users/manual-identity-validation-cost')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);

        $this->getJson('api/users/manual-identity-validation')
            ->assertUnauthorized();
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

        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => $paidAt,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'submitted_at' => $submittedAt,
            'review_note' => 'awaiting review',
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson('api/users/manual-identity-validation');

        $response->assertOk()
            ->assertJsonPath('has_submission', true)
            ->assertJsonPath('request_id', $row->id)
            ->assertJsonPath('paid', true)
            ->assertJsonPath('paid_at', $paidAt->toDateTimeString())
            ->assertJsonPath('review_status', ManualIdentityValidation::REVIEW_STATUS_PENDING)
            ->assertJsonPath('submitted_at', $submittedAt->toDateTimeString())
            ->assertJsonPath('review_note', 'awaiting review');
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
