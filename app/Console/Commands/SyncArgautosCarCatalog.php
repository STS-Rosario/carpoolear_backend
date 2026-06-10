<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use STS\Services\Argautos\CarCatalogSyncService;

class SyncArgautosCarCatalog extends Command
{
    protected $signature = 'car-catalog:sync-argautos {--mode=incremental : initial or incremental} {--dry-run : Report changes without writing}';

    protected $description = 'Sync car brands and models from Argautos API';

    public function handle(CarCatalogSyncService $service): int
    {
        $lock = Cache::lock(CarCatalogSyncService::LOCK_KEY, 7200);

        if (! $lock->get()) {
            $this->error('Another car catalog sync is already running.');

            return self::FAILURE;
        }

        try {
            $mode = (string) $this->option('mode');
            $dryRun = (bool) $this->option('dry-run');
            $service->storeStatus(['mode' => $mode, 'dry_run' => $dryRun], true);

            $summary = $service->sync($mode, $dryRun);
            $service->storeStatus($summary, false);

            $this->info(json_encode($summary, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $service->storeStatus(['mode' => $this->option('mode')], false, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }
}
