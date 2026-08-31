<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\DonationAmountAdjustment;
use STS\Services\PlatformDonationService;

class SyncDonationSubscriptionAmounts extends Command
{
    protected $signature = 'donations:sync-subscription-amounts {--adjustment-id=}';

    protected $description = 'Retry syncing donation subscription amounts after inflation updates';

    public function handle(PlatformDonationService $platformDonationService): int
    {
        $adjustmentId = $this->option('adjustment-id');

        $query = DonationAmountAdjustment::query()
            ->whereNull('applied_at')
            ->orWhere('subscriptions_failed', '>', 0);

        if ($adjustmentId) {
            $query = DonationAmountAdjustment::query()->where('id', $adjustmentId);
        }

        $count = 0;
        foreach ($query->get() as $adjustment) {
            $platformDonationService->syncSubscriptionAmountsForAdjustment($adjustment);
            $count++;
        }

        $this->info("Processed {$count} donation amount adjustment(s).");

        return self::SUCCESS;
    }
}
