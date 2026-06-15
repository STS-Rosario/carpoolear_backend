<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;
use STS\Support\ImageAttachmentRules;

class ImageUploadValidator
{
    /**
     * Validate an uploaded image file (MIME, extension, size).
     *
     * @param  list<string>|null  $allowedMimes
     * @param  list<string>|null  $allowedExtensions
     * @return array{valid: bool, errors?: array<string, array<string>>}
     */
    public function validate(
        UploadedFile $file,
        ?string $field = 'image',
        ?array $allowedMimes = null,
        ?array $allowedExtensions = null,
    ): array {
        $allowedMimes = $allowedMimes ?? config('carpoolear.image_upload_allowed_mimes', []);
        $allowedExtensions = $allowedExtensions ?? config('carpoolear.image_upload_allowed_extensions', []);
        $maxBytesRaw = config('carpoolear.image_upload_max_bytes');
        $maxBytes = (int) ($maxBytesRaw ?? 10 * 1024 * 1024);

        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();

        $errors = [];

        if (! $this->isAllowedMime($mime, $allowedMimes)) {
            $errors[$field] = ['Invalid image MIME type. Allowed: jpeg, png, webp, heic.'];
        }

        if (! $this->isAllowedExtension($extension, $allowedExtensions)) {
            $errors[$field] = $errors[$field] ?? ['Invalid image file extension. Allowed: jpeg, png, webp, heic.'];
        }

        if ($size === null || $size > $maxBytes) {
            $errors[$field] = $errors[$field] ?? ['File too large. Maximum size: '.($maxBytes / (1024 * 1024)).' MB.'];
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true];
    }

    /**
     * @param  list<string>  $allowedMimes
     */
    private function isAllowedMime(?string $mime, array $allowedMimes): bool
    {
        if ($mime === null || $mime === '') {
            return false;
        }

        if (in_array($mime, $allowedMimes, true)) {
            return true;
        }

        if (in_array($mime, ImageAttachmentRules::JPEG_MIMES, true)) {
            return ! empty(array_intersect($allowedMimes, ImageAttachmentRules::JPEG_MIMES));
        }

        return false;
    }

    /**
     * @param  list<string>  $allowedExtensions
     */
    private function isAllowedExtension(string $extension, array $allowedExtensions): bool
    {
        if (in_array($extension, $allowedExtensions, true)) {
            return true;
        }

        if (in_array($extension, ImageAttachmentRules::JPEG_EXTENSIONS, true)) {
            return ! empty(array_intersect($allowedExtensions, ImageAttachmentRules::JPEG_EXTENSIONS));
        }

        return false;
    }
}
