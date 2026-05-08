<?php

namespace Tests\Feature\Services;

use Carbon\Carbon;
use STS\Models\MaintenanceAuditLog;
use STS\Models\MaintenanceSchedule;
use STS\Models\MaintenanceState;
use STS\Services\Maintenance\MaintenanceStateService;
use Tests\TestCase;

class MaintenanceStateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tick_activates_due_pending_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00 UTC'));

        MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-05-10 11:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-05-10 14:00:00 UTC'),
            'message' => 'Planned work',
            'mode' => 'strict',
        ]);

        $service = app(MaintenanceStateService::class);
        $service->tick();

        $state = MaintenanceState::query()->find(1);
        $this->assertTrue($state->is_active);
        $this->assertSame('strict', $state->mode);
        $this->assertSame('Planned work', $state->message);
        $this->assertSame('schedule', $state->source);

        $this->assertDatabaseHas('maintenance_audit_logs', [
            'action' => 'cron_activate',
        ]);
    }

    public function test_tick_skips_cancelled_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00 UTC'));

        MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-05-10 11:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-05-10 14:00:00 UTC'),
            'message' => 'Cancelled',
            'mode' => 'strict',
            'cancelled_at' => Carbon::parse('2026-05-10 10:00:00 UTC'),
        ]);

        app(MaintenanceStateService::class)->tick();

        $this->assertFalse(MaintenanceState::query()->find(1)->is_active);
    }

    public function test_tick_deactivates_when_end_reached_and_marks_completed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00 UTC'));

        $schedule = MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-05-10 11:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-05-10 12:30:00 UTC'),
            'message' => 'Done soon',
            'mode' => 'flexible',
        ]);

        MaintenanceState::query()->whereKey(1)->update([
            'is_active' => true,
            'mode' => 'flexible',
            'message' => 'Done soon',
            'ends_at' => $schedule->ends_at,
            'source' => 'schedule',
            'active_schedule_id' => $schedule->id,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-10 12:31:00 UTC'));

        app(MaintenanceStateService::class)->tick();

        $state = MaintenanceState::query()->find(1);
        $this->assertFalse($state->is_active);

        $schedule->refresh();
        $this->assertNotNull($schedule->completed_at);

        $this->assertTrue(
            MaintenanceAuditLog::query()->where('action', 'cron_deactivate')->exists()
        );
    }

    public function test_overlaps_pending_schedule_detects_conflict(): void
    {
        MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-05-10 10:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-05-10 11:00:00 UTC'),
            'message' => 'A',
            'mode' => 'strict',
        ]);

        $this->assertSame(1, MaintenanceSchedule::query()->pending()->count());

        $service = app(MaintenanceStateService::class);

        $this->assertTrue($service->overlapsPendingSchedule(
            Carbon::parse('2026-05-10 10:30:00 UTC'),
            Carbon::parse('2026-05-10 12:00:00 UTC'),
            null
        ));

        $this->assertFalse($service->overlapsPendingSchedule(
            Carbon::parse('2026-05-10 11:00:00 UTC'),
            Carbon::parse('2026-05-10 12:00:00 UTC'),
            null
        ));
    }

    public function test_manual_disable_cancels_active_linked_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00 UTC'));

        $schedule = MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-05-10 11:00:00 UTC'),
            'ends_at' => null,
            'message' => 'Live',
            'mode' => 'flexible',
        ]);

        MaintenanceState::query()->whereKey(1)->update([
            'is_active' => true,
            'mode' => 'flexible',
            'message' => 'Live',
            'ends_at' => null,
            'source' => 'schedule',
            'active_schedule_id' => $schedule->id,
        ]);

        $service = app(MaintenanceStateService::class);
        $service->applyManualActive(false, null, null, null, 'manual', null, 1);

        $schedule->refresh();
        $this->assertNotNull($schedule->cancelled_at);
        $this->assertFalse(MaintenanceState::query()->find(1)->is_active);
    }
}
