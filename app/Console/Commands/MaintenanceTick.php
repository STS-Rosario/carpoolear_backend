<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Services\Maintenance\MaintenanceStateService;

class MaintenanceTick extends Command
{
    protected $signature = 'maintenance:tick';

    protected $description = 'Apply scheduled maintenance windows (start/end)';

    public function handle(MaintenanceStateService $maintenanceStateService): int
    {
        $maintenanceStateService->tick();

        return self::SUCCESS;
    }
}
