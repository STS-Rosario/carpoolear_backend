<?php

namespace STS\Services\Sellado;

final class SelladoChargeDecision
{
    public function __construct(
        private readonly bool $shouldCharge,
        private readonly string $reason,
    ) {}

    public function shouldCharge(): bool
    {
        return $this->shouldCharge;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function isClubMemberExempt(): bool
    {
        return $this->reason === SelladoChargePolicy::REASON_CLUB_MEMBER;
    }
}
