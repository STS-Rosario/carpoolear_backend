<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;

class HeicToJpegConverter
{
    private const HEIC_MIMES = ['image/heic', 'image/heif'];

    /**
     * Check if HEIC to JPEG conversion is available in this environment.
     * Requires Imagick with HEIC read/write support.
     */
    public static function isAvailable(): bool
    {
        $file = self::createValidHeicFile();
        if ($file === null) {
            return false;
        }
        try {
            $converter = new self();
            $result = $converter->convert($file);
            @unlink($file->getRealPath());

            return $result !== null && $result !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Create a valid minimal HEIC UploadedFile for testing.
     * Returns null if HEIC creation is not supported.
     *
     * @return UploadedFile|null
     */
    public static function createValidHeicFile(): ?UploadedFile
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }
        $formats = \Imagick::queryFormats('HEIC*');
        if (empty($formats)) {
            return null;
        }
        try {
            $imagick = new \Imagick();
            $imagick->newImage(1, 1, new \ImagickPixel('white'));
            $imagick->setImageFormat('heic');
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();
            if (empty($blob)) {
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'heic');
            file_put_contents($tmp, $blob);

            return new UploadedFile($tmp, 'test.heic', 'image/heic', null, true);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert HEIC/HEIF file to JPEG bytes. Returns null if conversion is disabled,
     * file is not HEIC/HEIF, or conversion is unavailable/fails.
     *
     * @return string|null JPEG binary content or null
     */
    public function convert(UploadedFile $file): ?string
    {
        if (! config('carpoolear.image_upload_convert_heic_to_jpeg', true)) {
            return null;
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::HEIC_MIMES, true)) {
            return null;
        }

        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $imagick = new \Imagick;
            $imagick->readImage($file->getRealPath());
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(90);
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            return $blob !== '' ? $blob : null;
        } catch (\Throwable $e) {
            \Log::warning('HEIC conversion failed: ' . $e->getMessage());

            return null;
        }
    }
}
