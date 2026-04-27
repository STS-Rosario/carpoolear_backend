<?php

namespace Tests\Unit\Models;

use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\User;
use Tests\TestCase;

class CampaignDonationTest extends TestCase
{
    private function makeCampaign(): Campaign
    {
        $slug = 'camp-'.uniqid('', true);

        return Campaign::query()->create([
            'slug' => $slug,
            'title' => 'Test campaign',
            'description' => 'Description for tests.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ]);
    }

    public function test_belongs_to_campaign_and_user(): void
    {
        $user = User::factory()->create();
        $campaign = $this->makeCampaign();

        $donation = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'pay-'.uniqid(),
            'amount_cents' => 500,
            'name' => 'Patron',
            'comment' => null,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);

        $this->assertTrue($donation->campaign->is($campaign));
        $this->assertTrue($donation->user->is($user));
    }

    public function test_amount_accessor_returns_dollars_from_cents(): void
    {
        $campaign = $this->makeCampaign();

        $donation = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => null,
            'amount_cents' => 1_250,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->assertSame(12.5, $donation->amount);
    }

    public function test_status_scopes_filter_rows(): void
    {
        $campaign = $this->makeCampaign();
        $user = User::factory()->create();

        $paid = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p1',
            'amount_cents' => 100,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);
        $pending = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p2',
            'amount_cents' => 200,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $failed = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p3',
            'amount_cents' => 300,
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        $this->assertTrue(CampaignDonation::paid()->whereKey($paid->id)->exists());
        $this->assertFalse(CampaignDonation::paid()->whereKey($pending->id)->exists());

        $this->assertTrue(CampaignDonation::pending()->whereKey($pending->id)->exists());
        $this->assertFalse(CampaignDonation::pending()->whereKey($paid->id)->exists());

        $this->assertTrue(CampaignDonation::failed()->whereKey($failed->id)->exists());
        $this->assertFalse(CampaignDonation::failed()->whereKey($paid->id)->exists());
    }
}
