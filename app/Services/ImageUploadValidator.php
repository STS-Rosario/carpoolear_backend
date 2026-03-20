<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;

class ImageUploadValidator
{
    /**
     * Validate an uploaded image file (MIME, extension, size).
     *
     * @return array{valid: bool, errors?: array<string, array<string>>}
     */
    public function validate(UploadedFile $file, ?string $field = 'image'): array
    {
        $allowedMimes = config('carpoolear.image_upload_allowed_mimes', []);
        $allowedExtensions = config('carpoolear.image_upload_allowed_extensions', []);
        $maxBytes = (int) config('carpoolear.image_upload_max_bytes', 10 * 1024 * 1024);

        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();

        $errors = [];

        if (! in_array($mime, $allowedMimes, true)) {
            $errors[$field] = ['Invalid image type. Allowed: jpeg, png, webp, heic.'];
        }

        if (! in_array($extension, $allowedExtensions, true)) {
            $errors[$field] = $errors[$field] ?? ['Invalid image type. Allowed: jpeg, png, webp, heic.'];
        }

        if ($size === null || $size > $maxBytes) {
            $errors[$field] = $errors[$field] ?? ['File too large. Maximum size: ' . ($maxBytes / (1024 * 1024)) . ' MB.'];
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }
}
