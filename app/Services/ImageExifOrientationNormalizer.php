<?php

namespace STS\Services;

class ImageExifOrientationNormalizer
{
    /**
     * Apply EXIF orientation to pixel data so viewers do not need Orientation metadata.
     */
    public function normalize(string $imageData): string
    {
        if ($imageData === '') {
            return $imageData;
        }

        $normalized = $this->normalizeWithImagick($imageData);
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->normalizeWithExifAndGd($imageData);
    }

    private function normalizeWithImagick(string $imageData): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick;
            $image->readImageBlob($imageData);
            $image->autoOrient();
            $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            $blob = $image->getImageBlob();
            $image->clear();
            $image->destroy();

            return $blob !== '' ? $blob : null;
        } catch (\Throwable $e) {
            \Log::warning('Imagick EXIF orientation normalization failed: '.$e->getMessage());

            return null;
        }
    }

    private function normalizeWithExifAndGd(string $imageData): string
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagecreatefromstring')) {
            return $imageData;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'exif-orient');
        if ($tmp === false) {
            return $imageData;
        }

        try {
            if (file_put_contents($tmp, $imageData) === false) {
                return $imageData;
            }

            $exif = @exif_read_data($tmp);
            if (! is_array($exif) || ! isset($exif['Orientation'])) {
                return $imageData;
            }

            $orientation = (int) $exif['Orientation'];
            if ($orientation < 2 || $orientation > 8) {
                return $imageData;
            }

            $image = @imagecreatefromstring($imageData);
            if ($image === false) {
                return $imageData;
            }

            $image = $this->applyOrientationWithGd($image, $orientation);
            if ($image === false) {
                return $imageData;
            }

            ob_start();
            imagejpeg($image, null, 90);
            $normalized = ob_get_clean();
            imagedestroy($image);

            return is_string($normalized) && $normalized !== '' ? $normalized : $imageData;
        } finally {
            @unlink($tmp);
        }
    }

    private function applyOrientationWithGd(\GdImage $image, int $orientation): \GdImage|false
    {
        return match ($orientation) {
            2 => $this->flipGdImage($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => $this->flipGdImage($image, IMG_FLIP_VERTICAL),
            5 => $this->transposeGdImage($image),
            6 => imagerotate($image, -90, 0),
            7 => $this->transverseGdImage($image),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function flipGdImage(\GdImage $image, int $mode): \GdImage|false
    {
        if (! imageflip($image, $mode)) {
            return false;
        }

        return $image;
    }

    private function transposeGdImage(\GdImage $image): \GdImage|false
    {
        $flipped = $this->flipGdImage($image, IMG_FLIP_HORIZONTAL);
        if ($flipped === false) {
            return false;
        }

        return imagerotate($flipped, -90, 0);
    }

    private function transverseGdImage(\GdImage $image): \GdImage|false
    {
        $flipped = $this->flipGdImage($image, IMG_FLIP_HORIZONTAL);
        if ($flipped === false) {
            return false;
        }

        return imagerotate($flipped, 90, 0);
    }
}
