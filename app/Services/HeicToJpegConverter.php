<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;

class HeicToJpegConverter
{
    private const HEIC_MIMES = ['image/heic', 'image/heif'];

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
