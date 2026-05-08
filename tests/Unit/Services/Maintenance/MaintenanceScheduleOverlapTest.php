<?php

namespace Tests\Unit\Services\Maintenance;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use STS\Services\Maintenance\MaintenanceInterval;

class MaintenanceScheduleOverlapTest extends TestCase
{
    public function test_adjacent_windows_do_not_overlap_half_open(): void
    {
        $aStart = Carbon::parse('2026-05-10 10:00:00 UTC');
        $aEnd = Carbon::parse('2026-05-10 11:00:00 UTC');
        $bStart = Carbon::parse('2026-05-10 11:00:00 UTC');
        $bEnd = Carbon::parse('2026-05-10 12:00:00 UTC');

        $this->assertFalse(MaintenanceInterval::overlap($aStart, $aEnd, $bStart, $bEnd));
    }

    public function test_overlapping_windows_detected(): void
    {
        $aStart = Carbon::parse('2026-05-10 10:00:00 UTC');
        $aEnd = Carbon::parse('2026-05-10 11:30:00 UTC');
        $bStart = Carbon::parse('2026-05-10 11:00:00 UTC');
        $bEnd = Carbon::parse('2026-05-10 12:00:00 UTC');

        $this->assertTrue(MaintenanceInterval::overlap($aStart, $aEnd, $bStart, $bEnd));
    }

    public function test_open_ended_overlaps_later_bounded_window(): void
    {
        $openStart = Carbon::parse('2026-05-10 10:00:00 UTC');
        $boundedStart = Carbon::parse('2026-05-10 11:00:00 UTC');
        $boundedEnd = Carbon::parse('2026-05-10 12:00:00 UTC');

        $this->assertTrue(MaintenanceInterval::overlap($openStart, null, $boundedStart, $boundedEnd));
    }

    public function test_bounded_before_open_ended_start_does_not_overlap(): void
    {
        $boundedStart = Carbon::parse('2026-05-10 09:00:00 UTC');
        $boundedEnd = Carbon::parse('2026-05-10 09:59:59 UTC');
        $openStart = Carbon::parse('2026-05-10 10:00:00 UTC');

        $this->assertFalse(MaintenanceInterval::overlap($boundedStart, $boundedEnd, $openStart, null));
    }
}
