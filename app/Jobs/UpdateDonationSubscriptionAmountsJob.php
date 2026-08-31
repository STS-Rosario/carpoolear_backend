<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use STS\Models\DonationAmountAdjustment;
use STS\Services\PlatformDonationService;

class UpdateDonationSubscriptionAmountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $adjustmentId) {}

    public function handle(PlatformDonationService $platformDonationService): void
    {
        $adjustment = DonationAmountAdjustment::find($this->adjustmentId);
        if (! $adjustment) {
            return;
        }

        $platformDonationService->syncSubscriptionAmountsForAdjustment($adjustment);
    }
}
