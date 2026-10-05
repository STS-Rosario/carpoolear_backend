<?php

namespace Tests\Feature\Http;

use Database\Seeders\DonationTierSeeder;
use STS\Http\Middleware\UserAdmin;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
use STS\Models\DonationTier;
use STS\Models\User;
use Tests\TestCase;

class AdminClubCarpoolearMembersApiTest extends TestCase
{
    private User $admin;

    private DonationTier $cafe;

    private DonationTier $beer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DonationTierSeeder::class);
        $this->cafe = DonationTier::where('slug', 'cafe')->firstOrFail();
        $this->beer = DonationTier::where('slug', 'beer')->firstOrFail();
        $this->admin = User::factory()->create(['is_admin' => true, 'admin_role' => 'superadmin']);
        $this->actingAs($this->admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_helpdesk_cannot_list_club_members(): void
    {
        $helpdesk = User::factory()->create(['is_admin' => true, 'admin_role' => 'helpdesk']);
        $this->actingAs($helpdesk, 'api');

        $this->getJson('/api/admin/club-carpoolear/members')->assertForbidden();
    }

    public function test_lists_current_members_with_last_payment_and_total(): void
    {
        $current = User::factory()->create([
            'name' => 'Current Member',
            'monthly_donate' => true,
            'club_carpoolear_joined_at' => '2026-01-15 09:00:00',
            'club_carpoolear_left_at' => null,
        ]);
        $former = User::factory()->create([
            'name' => 'Former Member',
            'monthly_donate' => false,
            'club_carpoolear_joined_at' => '2025-01-01 09:00:00',
            'club_carpoolear_left_at' => '2026-06-01 09:00:00',
        ]);

        $subscription = DonationSubscription::create([
            'user_id' => $current->id,
            'donation_tier_id' => $this->cafe->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
        ]);
        DonationSubscriptionCharge::create([
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => 'club-pay-1',
            'amount_cents' => 500000,
            'status' => 'approved',
            'charged_at' => '2026-03-01 10:00:00',
        ]);
        DonationPayment::create([
            'user_id' => $current->id,
            'donation_tier_id' => $this->beer->id,
            'amount_cents' => 750000,
            'status' => 'approved',
            'paid_at' => '2026-04-01 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/club-carpoolear/members')->assertOk();
        $rows = $response->json('data');
        $ids = collect($rows)->pluck('user_id')->all();
        $this->assertContains($current->id, $ids);
        $this->assertNotContains($former->id, $ids);

        $row = collect($rows)->firstWhere('user_id', $current->id);
        $this->assertSame('Current Member', $row['user_name']);
        $this->assertSame('cafe', $row['tier_slug']);
        $this->assertSame(1250000, $row['total_donated_cents']);
        $this->assertSame('2026-03-01 10:00:00', $row['last_paid_at']);
        $this->assertArrayNotHasKey('left_at', $row);
        $this->assertArrayHasKey('pagination', $response->json('meta'));
    }

    public function test_lists_former_members_with_leave_date(): void
    {
        $former = User::factory()->create([
            'name' => 'Former Member',
            'monthly_donate' => false,
            'club_carpoolear_joined_at' => '2025-01-01 09:00:00',
            'club_carpoolear_left_at' => '2026-06-01 12:00:00',
        ]);
        $current = User::factory()->create([
            'name' => 'Current Member',
            'monthly_donate' => true,
            'club_carpoolear_joined_at' => '2026-01-15 09:00:00',
            'club_carpoolear_left_at' => null,
        ]);

        $response = $this->getJson('/api/admin/club-carpoolear/members?status=former')->assertOk();
        $ids = collect($response->json('data'))->pluck('user_id')->all();
        $this->assertSame([$former->id], $ids);
        $this->assertSame('2026-06-01 12:00:00', $response->json('data.0.left_at'));
        $this->assertNotContains($current->id, $ids);
    }

    public function test_filters_current_members_by_name_and_tier(): void
    {
        $cafeMember = User::factory()->create([
            'name' => 'Cafe Person',
            'monthly_donate' => true,
            'club_carpoolear_joined_at' => now(),
            'club_carpoolear_left_at' => null,
        ]);
        $beerMember = User::factory()->create([
            'name' => 'Beer Person',
            'monthly_donate' => true,
            'club_carpoolear_joined_at' => now(),
            'club_carpoolear_left_at' => null,
        ]);
        DonationSubscription::create([
            'user_id' => $cafeMember->id,
            'donation_tier_id' => $this->cafe->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
        ]);
        DonationSubscription::create([
            'user_id' => $beerMember->id,
            'donation_tier_id' => $this->beer->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 750000,
        ]);

        $byName = $this->getJson('/api/admin/club-carpoolear/members?q=Cafe')->assertOk();
        $this->assertSame([$cafeMember->id], collect($byName->json('data'))->pluck('user_id')->all());

        $byTier = $this->getJson('/api/admin/club-carpoolear/members?tier=beer')->assertOk();
        $this->assertSame([$beerMember->id], collect($byTier->json('data'))->pluck('user_id')->all());
    }
}
