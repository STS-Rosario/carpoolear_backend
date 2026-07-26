<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\ManualIdentityValidation;
use STS\Services\ManualIdentityValidationDeletion;
use STS\Services\ManualIdentityValidationPhotosPurgedNotifier;

class PurgeRejectedManualIdentityValidationPhotos extends Command
{
    protected $signature = 'manual-identity-validation:purge-rejected-photos
                            {--days= : Number of days after rejection before purging photos}';

    protected $description = 'Purge photos from rejected manual identity validations after retention period';

    public function __construct(
        private readonly ManualIdentityValidationPhotosPurgedNotifier $photosPurgedNotifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7));
        $threshold = now()->subDays($days);

        $items = ManualIdentityValidation::query()
            ->with('user')
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
            ManualIdentityValidationDeletion::purgeStoredPhotos($item);
            $item->loadMissing('user');
            if ($item->user) {
                $this->photosPurgedNotifier->notify($item->user, $item);
            }
            $purgedCount++;
        }

        $this->info('Rejected manual identity validation photos purged: '.$purgedCount);

        return self::SUCCESS;
    }
}
