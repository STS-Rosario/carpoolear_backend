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

    public function test_uppercase_extension_is_accepted_after_normalization(): void
    {
        $file = UploadedFile::fake()->image('photo.JPG', 100, 100)->size(500);
        $result = $this->validator->validate($file);

        $this->assertTrue($result['valid']);
    }

    public function test_custom_config_restricts_mime_and_returns_expected_message(): void
    {
        config()->set('carpoolear.image_upload_allowed_mimes', ['image/png']);
        config()->set('carpoolear.image_upload_allowed_extensions', ['png']);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(500);
        $result = $this->validator->validate($file, 'avatar');

        $this->assertFalse($result['valid']);
        $this->assertSame(
            ['Invalid image type. Allowed: jpeg, png, webp, heic.'],
            $result['errors']['avatar']
        );
    }

    public function test_oversized_file_message_uses_configured_limit_mb(): void
    {
        config()->set('carpoolear.image_upload_max_bytes', 2 * 1024 * 1024);

        $file = UploadedFile::fake()->image('large.jpg', 1200, 1200)->size(2100);
        $result = $this->validator->validate($file, 'profile');

        $this->assertFalse($result['valid']);
        $this->assertSame(
            ['File too large. Maximum size: 2 MB.'],
            $result['errors']['profile']
        );
    }

    public function test_default_max_size_is_ten_mb_when_config_is_missing(): void
    {
        config()->offsetUnset('carpoolear.image_upload_max_bytes');

        $file = UploadedFile::fake()->image('large.jpg', 1400, 1400)->size(10300);
        $result = $this->validator->validate($file, 'avatar');

        $this->assertFalse($result['valid']);
        $this->assertSame(
            ['File too large. Maximum size: 10 MB.'],
            $result['errors']['avatar']
        );
    }

    public function test_max_size_config_is_cast_to_integer_bytes_before_validation(): void
    {
        config()->set('carpoolear.image_upload_max_bytes', '1048576');

        $file = UploadedFile::fake()->image('too-big.jpg', 900, 900)->size(1100);
        $result = $this->validator->validate($file, 'cover');

        $this->assertFalse($result['valid']);
        $this->assertSame(
            ['File too large. Maximum size: 1 MB.'],
            $result['errors']['cover']
        );
    }

    public function test_existing_type_error_is_not_overwritten_by_extension_or_size_errors(): void
    {
        config()->set('carpoolear.image_upload_max_bytes', 1 * 1024 * 1024);

        $file = UploadedFile::fake()->create('bad.exe', 2100, 'application/octet-stream');
        $result = $this->validator->validate($file, 'document');

        $this->assertFalse($result['valid']);
        $this->assertSame(
            ['Invalid image type. Allowed: jpeg, png, webp, heic.'],
            $result['errors']['document']
        );
    }
}
