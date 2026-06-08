<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use STS\Helpers\OngoingTripHelper;
use Tests\TestCase;

class OngoingTripHelperTest extends TestCase
{
    public function test_estimated_time_to_minutes_parses_hh_mm(): void
    {
        $this->assertSame(242, OngoingTripHelper::estimatedTimeToMinutes('04:02'));
        $this->assertSame(90, OngoingTripHelper::estimatedTimeToMinutes('01:30'));
    }

    public function test_estimated_time_to_minutes_returns_zero_when_missing(): void
    {
        $this->assertSame(0, OngoingTripHelper::estimatedTimeToMinutes(null));
        $this->assertSame(0, OngoingTripHelper::estimatedTimeToMinutes(''));
    }

    public function test_is_within_window_one_hour_before_start(): void
    {
        $start = Carbon::parse('2026-06-02 16:00:00');
        $now = $start->copy()->subMinutes(30);

        $this->assertTrue(
            OngoingTripHelper::isWithinOngoingTripWindow($now, $start, '01:00')
        );
    }

    public function test_is_within_window_during_trip(): void
    {
        $start = Carbon::parse('2026-06-02 16:00:00');
        $now = $start->copy()->addMinutes(20);

        $this->assertTrue(
            OngoingTripHelper::isWithinOngoingTripWindow($now, $start, '01:00')
        );
    }

    public function test_is_within_window_thirty_minutes_after_estimated_end(): void
    {
        $start = Carbon::parse('2026-06-02 16:00:00');
        $now = $start->copy()->addMinutes(80);

        $this->assertTrue(
            OngoingTripHelper::isWithinOngoingTripWindow($now, $start, '00:50')
        );
    }

    public function test_is_outside_window_more_than_one_hour_before_start(): void
    {
        $start = Carbon::parse('2026-06-02 16:00:00');
        $now = $start->copy()->subMinutes(61);

        $this->assertFalse(
            OngoingTripHelper::isWithinOngoingTripWindow($now, $start, '01:00')
        );
    }

    public function test_is_outside_window_more_than_thirty_minutes_after_estimated_end(): void
    {
        $start = Carbon::parse('2026-06-02 16:00:00');
        $now = $start->copy()->addMinutes(81);

        $this->assertFalse(
            OngoingTripHelper::isWithinOngoingTripWindow($now, $start, '00:50')
        );
    }
}
