<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use STS\Services\HeicToJpegConverter;
use Tests\TestCase;

class HeicToJpegConverterTest extends TestCase
{
    protected HeicToJpegConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new HeicToJpegConverter;
    }

    public function test_jpeg_file_returns_null_no_conversion(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $result = $this->converter->convert($file);
        $this->assertNull($result);
    }

    public function test_png_file_returns_null_no_conversion(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);
        $result = $this->converter->convert($file);
        $this->assertNull($result);
    }

    public function test_heic_file_when_conversion_disabled_returns_null(): void
    {
        config(['carpoolear.image_upload_convert_heic_to_jpeg' => false]);
        $file = UploadedFile::fake()->create('photo.heic', 100, 'image/heic');
        $result = $this->converter->convert($file);
        $this->assertNull($result);
        config(['carpoolear.image_upload_convert_heic_to_jpeg' => true]);
    }

    public function test_heic_file_returns_jpeg_or_null_depending_on_imagick_support(): void
    {
        $file = UploadedFile::fake()->create('photo.heic', 100, 'image/heic');
        $result = $this->converter->convert($file);
        if ($result !== null) {
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
            $this->assertStringStartsWith("\xff\xd8\xff", $result);
        } else {
            $this->assertNull($result);
        }
    }

    public function test_heif_file_returns_jpeg_or_null_depending_on_imagick_support(): void
    {
        $file = UploadedFile::fake()->create('photo.heif', 100, 'image/heif');
        $result = $this->converter->convert($file);
        if ($result !== null) {
            $this->assertIsString($result);
            $this->assertStringStartsWith("\xff\xd8\xff", $result);
        } else {
            $this->assertNull($result);
        }
    }

    public function test_heic_extension_with_non_heic_mime_does_not_convert(): void
    {
        $file = UploadedFile::fake()->create('photo.heic', 100, 'application/octet-stream');
        $result = $this->converter->convert($file);

        $this->assertNull($result);
    }

    public function test_create_valid_heic_file_returns_expected_shape_or_null_when_unavailable(): void
    {
        $file = HeicToJpegConverter::createValidHeicFile();

        if ($file === null) {
            $this->assertNull($file);

            return;
        }

        $this->assertSame('image/heic', $file->getMimeType());
        $this->assertSame('heic', strtolower($file->getClientOriginalExtension()));
        $this->assertFileExists($file->getRealPath());
    }

    public function test_is_available_reports_boolean_and_matches_real_conversion_capability(): void
    {
        $available = HeicToJpegConverter::isAvailable();
        $this->assertIsBool($available);

        $file = HeicToJpegConverter::createValidHeicFile();
        if ($file === null) {
            $this->assertFalse($available);

            return;
        }

        $result = $this->converter->convert($file);
        @unlink($file->getRealPath());

        $this->assertSame($result !== null && $result !== '', $available);
    }
}
