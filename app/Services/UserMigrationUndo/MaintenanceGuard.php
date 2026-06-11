<?php

namespace STS\Services\UserMigrationUndo;

use STS\Models\MaintenanceState;
use STS\Services\Maintenance\MaintenanceStateService;

class MaintenanceGuard
{
    public const MESSAGE = 'Volvemos pronto';

    private bool $enabledByCommand = false;

    public function __construct(
        private readonly MaintenanceStateService $maintenanceStateService,
    ) {}

    public function enableIfInactive(): void
    {
        if ($this->maintenanceStateService->state()->is_active) {
            return;
        }

        $this->maintenanceStateService->applyManualActive(
            true,
            'strict',
            self::MESSAGE,
            null,
            'manual',
            null,
            null
        );

        $this->enabledByCommand = true;
    }

    public function disableIfEnabledByCommand(): void
    {
        if (! $this->enabledByCommand) {
            return;
        }

        $this->maintenanceStateService->applyManualActive(
            false,
            null,
            null,
            null,
            'manual',
            null,
            null
        );

        $this->enabledByCommand = false;
    }

    public function wasEnabledByCommand(): bool
    {
        return $this->enabledByCommand;
    }

    public function isActive(): bool
    {
        return MaintenanceState::query()->findOrFail(1)->is_active;
    }
}
