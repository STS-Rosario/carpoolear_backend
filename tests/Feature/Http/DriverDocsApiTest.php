<?php

namespace Tests\Feature\Http;

use Illuminate\Http\UploadedFile;
use STS\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DriverDocsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['carpoolear.module_validated_drivers' => true]);
    }

    public function test_create_user_with_valid_driver_doc_succeeds(): void
    {
        $file = UploadedFile::fake()->image('doc.jpg', 100, 100)->size(500);
        $data = [
            'name' => 'Driver User',
            'email' => 'driver' . time() . '@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'driver_data_docs' => [$file],
        ];

        $response = $this->post('api/users', $data);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $user = User::where('email', $data['email'])->first();
        $this->assertNotNull($user);
        $this->assertNotEmpty($user->driver_data_docs);
        $docs = json_decode($user->driver_data_docs, true);
        $this->assertIsArray($docs);
        $this->assertCount(1, $docs);
        $storedPath = base_path('public/image/docs/' . $docs[0]);
        $this->assertFileExists($storedPath);
    }

    public function test_create_user_with_disallowed_file_type_returns_422(): void
    {
        $file = UploadedFile::fake()->create('document.exe', 100, 'application/octet-stream');
        $data = [
            'name' => 'Driver User',
            'email' => 'driver' . time() . '@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'driver_data_docs' => [$file],
        ];

        $response = $this->post('api/users', $data);

        $response->assertStatus(422);
        $user = User::where('email', $data['email'])->first();
        $this->assertNull($user);
    }

    public function test_create_user_with_file_over_size_returns_422(): void
    {
        $file = UploadedFile::fake()->image('large.jpg', 1000, 1000)->size(1024 * 11);
        $data = [
            'name' => 'Driver User',
            'email' => 'driver' . time() . '@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'driver_data_docs' => [$file],
        ];

        $response = $this->post('api/users', $data);

        $response->assertStatus(422);
        $user = User::where('email', $data['email'])->first();
        $this->assertNull($user);
    }

    public function test_create_user_with_heic_stores_as_jpeg_when_conversion_available(): void
    {
        config(['carpoolear.image_upload_convert_heic_to_jpeg' => true]);
        $file = UploadedFile::fake()->create('doc.heic', 100, 'image/heic');
        $data = [
            'name' => 'Driver User',
            'email' => 'driver' . time() . '@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'driver_data_docs' => [$file],
        ];

        $response = $this->post('api/users', $data);

        $response->assertStatus(200);
        $user = User::where('email', $data['email'])->first();
        $this->assertNotNull($user);
        $docs = json_decode($user->driver_data_docs, true);
        $this->assertIsArray($docs);
        $this->assertCount(1, $docs);
        $filename = $docs[0];
        $this->assertStringEndsWith('.jpg', $filename);
    }
}
