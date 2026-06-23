<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use STS\Helpers\RatingHelper;
use STS\Models\Rating;
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

    public function test_rating_requires_comment_for_negative_and_neutral_votes_only(): void
    {
        $this->assertTrue(RatingHelper::ratingRequiresComment(Rating::STATE_NEGATIVO));
        $this->assertTrue(RatingHelper::ratingRequiresComment(Rating::STATE_NEUTRAL));
        $this->assertFalse(RatingHelper::ratingRequiresComment(Rating::STATE_POSITIVO));
        $this->assertFalse(RatingHelper::ratingRequiresComment(null));
    }

    public function test_has_required_rating_comment_rejects_empty_or_whitespace_for_negative_and_neutral(): void
    {
        $this->assertFalse(RatingHelper::hasRequiredRatingComment(Rating::STATE_NEGATIVO, null));
        $this->assertFalse(RatingHelper::hasRequiredRatingComment(Rating::STATE_NEGATIVO, '   '));
        $this->assertTrue(RatingHelper::hasRequiredRatingComment(Rating::STATE_NEGATIVO, 'Bad trip'));

        $this->assertFalse(RatingHelper::hasRequiredRatingComment(Rating::STATE_NEUTRAL, ''));
        $this->assertFalse(RatingHelper::hasRequiredRatingComment(Rating::STATE_NEUTRAL, '   '));
        $this->assertTrue(RatingHelper::hasRequiredRatingComment(Rating::STATE_NEUTRAL, 'Average trip'));

        $this->assertTrue(RatingHelper::hasRequiredRatingComment(Rating::STATE_POSITIVO, null));
        $this->assertTrue(RatingHelper::hasRequiredRatingComment(Rating::STATE_POSITIVO, ''));
    }
}
