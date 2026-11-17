<?php

namespace Tests\Unit\Services\Sellado;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use STS\Models\DonationSubscription;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Sellado\SelladoChargePolicy;
use Tests\TestCase;

class SelladoChargePolicyTest extends TestCase
{
    private SelladoChargePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(SelladoChargePolicy::class);
        Config::set('carpoolear.module_trip_creation_payment_enabled', true);
        Config::set('carpoolear.module_trip_creation_payment_trips_threshold', 2);
    }

    public function test_disabled_when_module_off(): void
    {
        Config::set('carpoolear.module_trip_creation_payment_enabled', false);
        $user = User::factory()->create();

        $decision = $this->policy->decide($user, true);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_DISABLED, $decision->reason());
    }

    public function test_route_not_eligible_when_route_does_not_need_payment(): void
    {
        $user = User::factory()->create();

        $decision = $this->policy->decide($user, false);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_ROUTE_NOT_ELIGIBLE, $decision->reason());
    }

    public function test_club_member_exempt_when_monthly_donate(): void
    {
        $user = User::factory()->create(['monthly_donate' => true]);
        Trip::factory()->count(2)->create(['user_id' => $user->id]);

        $decision = $this->policy->decide($user, true);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_CLUB_MEMBER, $decision->reason());
    }

    public function test_club_member_exempt_when_authorized_subscription(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        DonationSubscription::create([
            'user_id' => $user->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
        ]);
        Trip::factory()->count(2)->create(['user_id' => $user->id]);

        $decision = $this->policy->decide($user->fresh(), true);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_CLUB_MEMBER, $decision->reason());
    }

    public function test_club_member_wins_over_free_trip_threshold(): void
    {
        $user = User::factory()->create(['monthly_donate' => true]);
        // Over threshold would otherwise charge
        Trip::factory()->count(5)->create(['user_id' => $user->id]);

        $decision = $this->policy->decide($user, true);

        $this->assertSame(SelladoChargePolicy::REASON_CLUB_MEMBER, $decision->reason());
        $this->assertFalse($decision->shouldCharge());
    }

    public function test_under_threshold_when_free_trips_remain(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        Trip::factory()->create(['user_id' => $user->id]);

        $decision = $this->policy->decide($user, true);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_UNDER_THRESHOLD, $decision->reason());
    }

    public function test_complimentary_credit_when_over_threshold_with_unused_credit(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        Trip::factory()->count(2)->create(['user_id' => $user->id]);
        Trip::factory()->create([
            'user_id' => $user->id,
            'is_passenger' => false,
            'needs_sellado' => true,
            'state' => Trip::STATE_READY,
            'trip_date' => Carbon::now()->subDay(),
        ]);

        $decision = $this->policy->decide($user, true);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_COMPLIMENTARY_CREDIT, $decision->reason());
    }

    public function test_charge_when_over_threshold_and_not_club_member(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        Trip::factory()->count(2)->create(['user_id' => $user->id]);

        $decision = $this->policy->decide($user, true);

        $this->assertTrue($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_CHARGE, $decision->reason());
    }

    public function test_exclude_trip_id_is_passed_to_trip_count(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        $existing = Trip::factory()->create(['user_id' => $user->id]);
        // Only one other trip would leave user under threshold of 2 if existing is excluded
        // With excludeTripId = existing->id and 0 other trips → under threshold
        $decision = $this->policy->decide($user, true, $existing->id);

        $this->assertFalse($decision->shouldCharge());
        $this->assertSame(SelladoChargePolicy::REASON_UNDER_THRESHOLD, $decision->reason());
    }

    public function test_includes_sellado_in_pricing_for_non_member_on_eligible_route(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);

        $this->assertTrue($this->policy->includesSelladoInPricing($user, true));
    }

    public function test_omits_sellado_from_pricing_for_club_member(): void
    {
        $user = User::factory()->create(['monthly_donate' => true]);

        $this->assertFalse($this->policy->includesSelladoInPricing($user, true));
    }

    public function test_includes_sellado_in_pricing_when_user_unknown_on_eligible_route(): void
    {
        $this->assertTrue($this->policy->includesSelladoInPricing(null, true));
    }
}
