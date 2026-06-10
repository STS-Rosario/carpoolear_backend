<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use STS\Helpers\RatingHelper;
use Tests\TestCase;

class RatingHelperTest extends TestCase
{
    public function test_get_rating_available_at_is_trip_start_plus_eighty_percent_of_estimated_time(): void
    {
        $tripStart = Carbon::parse('2026-06-01 10:00:00');

        $availableAt = RatingHelper::getRatingAvailableAt($tripStart, '02:00');

        $this->assertSame('2026-06-01 11:36:00', $availableAt->format('Y-m-d H:i:s'));
    }

    public function test_get_rating_available_at_returns_trip_start_when_estimated_time_is_missing(): void
    {
        $tripStart = Carbon::parse('2026-06-01 10:00:00');

        $this->assertSame(
            '2026-06-01 10:00:00',
            RatingHelper::getRatingAvailableAt($tripStart, null)->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-06-01 10:00:00',
            RatingHelper::getRatingAvailableAt($tripStart, '')->format('Y-m-d H:i:s')
        );
    }

    public function test_is_rating_available_returns_true_when_now_is_after_available_at(): void
    {
        $tripStart = Carbon::parse('2026-06-01 10:00:00');
        $now = Carbon::parse('2026-06-01 11:36:00');

        $this->assertTrue(RatingHelper::isRatingAvailable($now, $tripStart, '02:00'));
    }

    public function test_is_rating_available_returns_false_before_eighty_percent_of_estimated_time(): void
    {
        $tripStart = Carbon::parse('2026-06-01 10:00:00');
        $now = Carbon::parse('2026-06-01 11:35:00');

        $this->assertFalse(RatingHelper::isRatingAvailable($now, $tripStart, '02:00'));
    }
}
