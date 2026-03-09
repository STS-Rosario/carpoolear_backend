<?php

namespace Tests\Feature\Http;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ManualIdentityValidationApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['carpoolear.identity_validation_manual_enabled' => true]);
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
        config(['carpoolear.image_upload_convert_heic_to_jpeg' => true]);
        $user = User::factory()->create();
        $validationRequest = $this->createPaidValidationRequest($user);

        $front = UploadedFile::fake()->create('front.heic', 100, 'image/heic');
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
    }
}
