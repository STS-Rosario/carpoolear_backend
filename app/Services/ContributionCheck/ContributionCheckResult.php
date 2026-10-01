<?php

namespace STS\Services\ContributionCheck;

final class ContributionCheckResult
{
    public function __construct(
        public readonly ?float $suspectedContribution,
        public readonly bool $exceedsMax,
        public readonly bool $phoneInDescription,
    ) {}
}
