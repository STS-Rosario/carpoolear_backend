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

class AdminDonationLedgerApiTest extends TestCase
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

    public function test_helpdesk_cannot_read_the_donation_ledger(): void
    {
        $helpdesk = User::factory()->create(['is_admin' => true, 'admin_role' => 'helpdesk']);
        $this->actingAs($helpdesk, 'api');

        $this->getJson('/api/admin/donations/payments')->assertForbidden();
    }

    public function test_ledger_unions_one_off_and_club_charges_newest_first(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Donor']);
        $bruno = User::factory()->create(['name' => 'Bruno Club']);

        DonationPayment::create([
            'user_id' => $ana->id,
            'donation_tier_id' => $this->cafe->id,
            'amount_cents' => 500000,
            'status' => 'approved',
            'source' => 'aportar',
            'mp_payment_id' => 'mp-once-1',
            'paid_at' => '2026-09-01 10:00:00',
        ]);

        $subscription = DonationSubscription::create([
            'user_id' => $bruno->id,
            'donation_tier_id' => $this->beer->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 750000,
            'source' => 'donate_page',
        ]);
        DonationSubscriptionCharge::create([
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => 'mp-club-1',
            'amount_cents' => 750000,
            'status' => 'approved',
            'charged_at' => '2026-09-10 12:00:00',
        ]);

        $response = $this->getJson('/api/admin/donations/payments?per_page=10');

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertCount(2, $rows);
        $this->assertSame('club', $rows[0]['kind']);
        $this->assertSame($bruno->id, $rows[0]['user_id']);
        $this->assertSame('Bruno Club', $rows[0]['user_name']);
        $this->assertSame(750000, $rows[0]['amount_cents']);
        $this->assertSame('beer', $rows[0]['tier_slug']);
        $this->assertSame('unica_vez', $rows[1]['kind']);
        $this->assertSame('Ana Donor', $rows[1]['user_name']);
        $this->assertSame('cafe', $rows[1]['tier_slug']);
        $this->assertSame('aportar', $rows[1]['source']);
        $this->assertArrayHasKey('pagination', $response->json('meta'));
    }

    public function test_ledger_filters_by_kind_status_search_and_user(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Donor']);
        $bruno = User::factory()->create(['name' => 'Bruno Club']);

        DonationPayment::create([
            'user_id' => $ana->id,
            'donation_tier_id' => $this->cafe->id,
            'amount_cents' => 500000,
            'status' => 'pending',
            'paid_at' => '2026-09-01 10:00:00',
        ]);
        DonationPayment::create([
            'user_id' => $ana->id,
            'donation_tier_id' => $this->cafe->id,
            'amount_cents' => 500000,
            'status' => 'approved',
            'paid_at' => '2026-09-02 10:00:00',
        ]);
        $subscription = DonationSubscription::create([
            'user_id' => $bruno->id,
            'donation_tier_id' => $this->beer->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 750000,
        ]);
        DonationSubscriptionCharge::create([
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => 'mp-club-filter',
            'amount_cents' => 750000,
            'status' => 'approved',
            'charged_at' => '2026-09-03 10:00:00',
        ]);

        $kind = $this->getJson('/api/admin/donations/payments?kind=unica_vez&status=approved')->assertOk();
        $this->assertCount(1, $kind->json('data'));
        $this->assertSame('unica_vez', $kind->json('data.0.kind'));

        $search = $this->getJson('/api/admin/donations/payments?q=Bruno')->assertOk();
        $this->assertCount(1, $search->json('data'));
        $this->assertSame($bruno->id, $search->json('data.0.user_id'));

        $user = $this->getJson('/api/admin/donations/payments?user_id='.$ana->id)->assertOk();
        $this->assertCount(2, $user->json('data'));
    }

    public function test_ledger_sorts_by_amount(): void
    {
        $user = User::factory()->create(['name' => 'Sort User']);
        DonationPayment::create([
            'user_id' => $user->id,
            'donation_tier_id' => $this->cafe->id,
            'amount_cents' => 500000,
            'status' => 'approved',
            'paid_at' => '2026-09-01 10:00:00',
        ]);
        DonationPayment::create([
            'user_id' => $user->id,
            'donation_tier_id' => $this->beer->id,
            'amount_cents' => 750000,
            'status' => 'approved',
            'paid_at' => '2026-09-02 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/donations/payments?sort=amount_cents&direction=desc')->assertOk();
        $amounts = collect($response->json('data'))->pluck('amount_cents')->all();
        $this->assertSame([750000, 500000], $amounts);
    }
}
