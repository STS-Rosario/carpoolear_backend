<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use STS\Services\Argautos\CarCatalogSyncService;

class SyncArgautosCarCatalogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(CarCatalogSyncService $service): void
    {
        $lock = Cache::lock(CarCatalogSyncService::LOCK_KEY, 7200);

        if (! $lock->get()) {
            return;
        }

        try {
            $service->storeStatus(['mode' => 'incremental'], true);
            $summary = $service->sync('incremental', false);
            $service->storeStatus($summary, false);
        } catch (\Throwable $e) {
            $service->storeStatus(['mode' => 'incremental'], false, $e->getMessage());
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }
}
