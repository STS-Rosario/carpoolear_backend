<?php

namespace STS\Services\Sellado;

use STS\Helpers\SelladoEmptyTripCredit;
use STS\Helpers\SelladoTripCount;
use STS\Models\User;
use STS\Services\ClubCarpoolearMembershipService;

class SelladoChargePolicy
{
    public const REASON_DISABLED = 'disabled';

    public const REASON_ROUTE_NOT_ELIGIBLE = 'route_not_eligible';

    public const REASON_CLUB_MEMBER = 'club_member';

    public const REASON_UNDER_THRESHOLD = 'under_threshold';

    public const REASON_COMPLIMENTARY_CREDIT = 'complimentary_credit';

    public const REASON_CHARGE = 'charge';

    public function __construct(
        private readonly ClubCarpoolearMembershipService $clubMembership,
    ) {}

    public function decide(User $user, bool $routeNeedsPayment, ?int $excludeTripId = null): SelladoChargeDecision
    {
        if (! config('carpoolear.module_trip_creation_payment_enabled')) {
            return new SelladoChargeDecision(false, self::REASON_DISABLED);
        }

        if (! $routeNeedsPayment) {
            return new SelladoChargeDecision(false, self::REASON_ROUTE_NOT_ELIGIBLE);
        }

        if ($this->clubMembership->isActiveMember($user)) {
            return new SelladoChargeDecision(false, self::REASON_CLUB_MEMBER);
        }

        $tripsCreatedByUser = SelladoTripCount::forUser((int) $user->id, $excludeTripId);
        if ($tripsCreatedByUser < (int) config('carpoolear.module_trip_creation_payment_trips_threshold')) {
            return new SelladoChargeDecision(false, self::REASON_UNDER_THRESHOLD);
        }

        if (SelladoEmptyTripCredit::userHasUnusedCredit((int) $user->id)) {
            return new SelladoChargeDecision(false, self::REASON_COMPLIMENTARY_CREDIT);
        }

        return new SelladoChargeDecision(true, self::REASON_CHARGE);
    }
}
