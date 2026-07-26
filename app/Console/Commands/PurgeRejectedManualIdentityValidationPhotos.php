<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use STS\Models\ManualIdentityValidation;

class PurgeRejectedManualIdentityValidationPhotos extends Command
{
    protected $signature = 'manual-identity-validation:purge-rejected-photos
                            {--days= : Number of days after rejection before purging photos}';

    protected $description = 'Purge photos from rejected manual identity validations after retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7));
        $threshold = now()->subDays($days);

        $items = ManualIdentityValidation::query()
            ->where('review_status', ManualIdentityValidation::REVIEW_STATUS_REJECTED)
            ->whereNull('images_purged_at')
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<=', $threshold)
            ->where(function ($query) {
                $query->whereNotNull('front_image_path')
                    ->orWhereNotNull('back_image_path')
                    ->orWhereNotNull('selfie_image_path');
            })
            ->get();

        $purgedCount = 0;

        foreach ($items as $item) {
            $this->purgePhotos($item);
            $purgedCount++;
        }

        $this->info('Rejected manual identity validation photos purged: '.$purgedCount);

        return self::SUCCESS;
    }

    private function purgePhotos(ManualIdentityValidation $item): void
    {
        foreach (['front_image_path', 'back_image_path', 'selfie_image_path'] as $column) {
            $path = $item->$column;
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            $item->$column = null;
        }

        $item->images_purged_at = now();
        $item->save();
    }
}
