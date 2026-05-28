<?php

namespace Tests\Unit\Services;

use STS\Services\ImageExifOrientationNormalizer;
use Tests\TestCase;

class ImageExifOrientationNormalizerTest extends TestCase
{
    private ImageExifOrientationNormalizer $normalizer;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ImageExifOrientationNormalizer;
        $this->fixturesPath = base_path('tests/fixtures');
    }

    public function test_normalize_corrects_exif_orientation_6_by_rotating_pixels(): void
    {
        $orientedJpeg = file_get_contents($this->fixturesPath.'/orientation_6.jpg');
        $this->assertNotFalse($orientedJpeg);

        $exif = @exif_read_data($this->fixturesPath.'/orientation_6.jpg');
        $this->assertSame(6, (int) ($exif['Orientation'] ?? 0));

        $stored = getimagesizefromstring($orientedJpeg);
        $this->assertSame(600, $stored[0]);
        $this->assertSame(450, $stored[1]);

        $normalized = $this->normalizer->normalize($orientedJpeg);

        $size = getimagesizefromstring($normalized);
        $this->assertNotFalse($size);
        $this->assertSame(450, $size[0]);
        $this->assertSame(600, $size[1]);
    }

    public function test_normalize_leaves_orientation_1_unchanged(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick required to build JPEG fixture.');
        }

        $jpeg = $this->createJpegWithExifOrientation(80, 120, \Imagick::ORIENTATION_TOPLEFT);

        $normalized = $this->normalizer->normalize($jpeg);

        $size = getimagesizefromstring($normalized);
        $this->assertNotFalse($size);
        $this->assertSame(80, $size[0]);
        $this->assertSame(120, $size[1]);
    }

    private function createJpegWithExifOrientation(int $width, int $height, int $orientation): string
    {
        $image = new \Imagick;
        $image->newImage($width, $height, new \ImagickPixel('red'));
        $image->setImageFormat('jpeg');
        $image->setImageOrientation($orientation);
        $blob = $image->getImageBlob();
        $image->clear();
        $image->destroy();

        return $blob;
    }
}
