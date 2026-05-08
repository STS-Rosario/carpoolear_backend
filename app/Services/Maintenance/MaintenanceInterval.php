<?php

namespace STS\Services\Maintenance;

use Carbon\Carbon;

final class MaintenanceInterval
{
    /**
     * Half-open intervals [start, end): they overlap iff they share any instant.
     * Null end means unbounded forward (treated as far-future for comparison).
     */
    public static function overlap(Carbon $startA, ?Carbon $endA, Carbon $startB, ?Carbon $endB): bool
    {
        $farFuture = Carbon::create(9999, 12, 31, 23, 59, 59, 'UTC');
        $effEndA = $endA ?? $farFuture;
        $effEndB = $endB ?? $farFuture;

        return $startA->lt($effEndB) && $startB->lt($effEndA);
    }
}
