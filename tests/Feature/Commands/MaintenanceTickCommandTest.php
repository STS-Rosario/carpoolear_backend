<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use STS\Models\MaintenanceSchedule;
use STS\Models\MaintenanceState;
use Tests\TestCase;

class MaintenanceTickCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_maintenance_tick_invokes_scheduler_tick(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00 UTC'));

        MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-05-10 11:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-05-10 14:00:00 UTC'),
            'message' => 'Cmd test',
            'mode' => 'strict',
        ]);

        $this->artisan('maintenance:tick')->assertSuccessful();

        $this->assertTrue(MaintenanceState::query()->find(1)->is_active);
    }
}
