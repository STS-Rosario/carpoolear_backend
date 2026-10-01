<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\ManualIdentityValidation;
use STS\Services\ManualIdentityValidationUploadReminderNotifier;

class RemindManualIdentityValidationPhotoUpload extends Command
{
    protected $signature = 'manual-identity-validation:remind-upload-photos';

    protected $description = 'Remind users who paid for manual identity validation but have not uploaded photos';

    public function __construct(
        private readonly ManualIdentityValidationUploadReminderNotifier $uploadReminderNotifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $week2Threshold = now()->subDays(14);
        $week1Threshold = now()->subDays(7);

        $items = ManualIdentityValidation::query()
            ->with('user')
            ->where('paid', true)
            ->whereNotNull('paid_at')
            ->whereNull('submitted_at')
            ->where(function ($query) {
                $query->whereNull('review_status')
                    ->orWhereNotIn('review_status', ['approved', 'approve', 'rejected', 'reject', 'closed']);
            })
            ->where(function ($query) use ($week1Threshold, $week2Threshold) {
                $query->where(function ($week2) use ($week2Threshold) {
                    $week2->where('paid_at', '<=', $week2Threshold)
                        ->whereNull('photos_upload_reminder_week2_sent_at');
                })->orWhere(function ($week1) use ($week1Threshold) {
                    $week1->where('paid_at', '<=', $week1Threshold)
                        ->whereNull('photos_upload_reminder_week1_sent_at');
                });
            })
            ->get();

        $sentCount = 0;

        foreach ($items as $item) {
            if (! $item->user) {
                continue;
            }

            $this->uploadReminderNotifier->notify($item->user, $item);

            $sentAt = now();
            $week2Due = $item->paid_at !== null
                && $item->paid_at->lte($week2Threshold)
                && $item->photos_upload_reminder_week2_sent_at === null;

            if ($week2Due) {
                $item->photos_upload_reminder_week2_sent_at = $sentAt;
                if ($item->photos_upload_reminder_week1_sent_at === null) {
                    $item->photos_upload_reminder_week1_sent_at = $sentAt;
                }
            } else {
                $item->photos_upload_reminder_week1_sent_at = $sentAt;
            }

            $item->save();
            $sentCount++;
        }

        $this->info('Manual identity validation photo upload reminders sent: '.$sentCount);

        return self::SUCCESS;
    }
}
