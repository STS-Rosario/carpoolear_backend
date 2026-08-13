<?php

namespace Tests\Unit\Support;

use STS\Support\TripExcessContributionStatus;
use Tests\TestCase;

class TripExcessContributionStatusTest extends TestCase
{
    public function test_all_statuses_include_expected_values(): void
    {
        $this->assertSame([
            'pendiente',
            'resuelto',
            'descartado',
            'en_proceso',
        ], TripExcessContributionStatus::ALL);
    }
}
