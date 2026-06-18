<?php

namespace STS\Services;

use Illuminate\Support\Facades\Storage;
use STS\Models\ManualIdentityValidation;

class ManualIdentityValidationDeletion
{
    public static function deleteRecords(iterable $records): void
    {
        foreach ($records as $item) {
            self::deleteRecord($item);
        }
    }

    public static function deleteRecord(ManualIdentityValidation $item): void
    {
        foreach (['front_image_path', 'back_image_path', 'selfie_image_path'] as $column) {
            $path = $item->$column;
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $item->delete();
    }
}
