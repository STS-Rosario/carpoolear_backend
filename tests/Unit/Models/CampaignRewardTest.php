<?php

namespace Tests\Unit\Models;

use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignReward;
use Tests\TestCase;

class CampaignRewardTest extends TestCase
{
    private function makeCampaign(): Campaign
    {
        return Campaign::query()->create([
            'slug' => 'rw-'.uniqid('', true),
            'title' => 'Reward campaign',
            'description' => 'For reward tests.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ]);
    }

    private function makeReward(Campaign $campaign, array $overrides = []): CampaignReward
    {
        return CampaignReward::query()->create(array_merge([
            'campaign_id' => $campaign->id,
            'title' => 'Sticker pack',
            'description' => 'Shipped perk',
            'donation_amount_cents' => 1_500,
            'quantity_available' => null,
            'is_active' => true,
        ], $overrides));
    }

    public function test_belongs_to_campaign_and_casts(): void
    {
        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign, [
            'donation_amount_cents' => '2000',
            'quantity_available' => '5',
            'is_active' => '1',
        ]);

        $reward = $reward->fresh();
        $this->assertTrue($reward->campaign->is($campaign));
        $this->assertSame(2_000, $reward->donation_amount_cents);
        $this->assertSame(5, $reward->quantity_available);
        $this->assertTrue($reward->is_active);
    }

    public function test_donation_amount_accessor_is_dollars_from_cents(): void
    {
        $reward = $this->makeReward($this->makeCampaign(), [
            'donation_amount_cents' => 1_999,
        ]);

        $this->assertSame(19.99, $reward->fresh()->donation_amount);
    }

    public function test_unlimited_quantity_is_never_sold_out_and_remaining_null(): void
    {
        $reward = $this->makeReward($this->makeCampaign(), [
            'quantity_available' => null,
        ]);

        $reward = $reward->fresh();
        $this->assertFalse($reward->is_sold_out);
        $this->assertNull($reward->quantity_remaining);
    }

    public function test_quantity_remaining_and_sold_out_follow_paid_donations(): void
    {
        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign, [
            'quantity_available' => 2,
        ]);

        $base = [
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'payment_id' => null,
            'amount_cents' => 1_500,
            'user_id' => null,
        ];

        CampaignDonation::query()->create(array_merge($base, [
            'status' => 'paid',
            'payment_id' => 'a',
        ]));

        $reward = $reward->fresh();
        $this->assertSame(1, $reward->quantity_remaining);
        $this->assertFalse($reward->is_sold_out);

        CampaignDonation::query()->create(array_merge($base, [
            'status' => 'paid',
            'payment_id' => 'b',
        ]));

        $reward = $reward->fresh();
        $this->assertSame(0, $reward->quantity_remaining);
        $this->assertTrue($reward->is_sold_out);

        CampaignDonation::query()->create(array_merge($base, [
            'status' => 'pending',
            'payment_id' => 'c',
        ]));

        $this->assertTrue($reward->fresh()->is_sold_out, 'Pending purchases must not free inventory');
    }
}
