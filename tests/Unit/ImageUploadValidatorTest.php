<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use STS\Services\ImageUploadValidator;
use Tests\TestCase;

class ImageUploadValidatorTest extends TestCase
{
    protected ImageUploadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImageUploadValidator;
    }

    public function test_valid_jpeg_passes(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(500);
        $result = $this->validator->validate($file);
        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('errors', $result);
    }

    public function test_valid_png_passes(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100)->size(500);
        $result = $this->validator->validate($file);
        $this->assertTrue($result['valid']);
    }

    public function test_disallowed_mime_fails(): void
    {
        $file = UploadedFile::fake()->create('document.gif', 100, 'image/gif');
        $result = $this->validator->validate($file);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_disallowed_extension_fails(): void
    {
        $file = UploadedFile::fake()->create('document.exe', 100, 'application/octet-stream');
        $result = $this->validator->validate($file);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_file_over_size_limit_fails(): void
    {
        $file = UploadedFile::fake()->image('large.jpg', 1000, 1000)->size(1024 * 11);
        $result = $this->validator->validate($file);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_file_at_size_limit_passes(): void
    {
        $maxKb = (int) (config('carpoolear.image_upload_max_bytes', 10 * 1024 * 1024) / 1024);
        $file = UploadedFile::fake()->image('at_limit.jpg', 100, 100)->size($maxKb);
        $result = $this->validator->validate($file);
        $this->assertTrue($result['valid']);
    }

    public function test_validate_with_field_name_returns_errors_keyed_by_field(): void
    {
        $file = UploadedFile::fake()->create('bad.exe', 100, 'application/octet-stream');
        $result = $this->validator->validate($file, 'front_image');
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('front_image', $result['errors']);
    }
}
