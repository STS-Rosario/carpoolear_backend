<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignMilestone;
use STS\Models\CampaignReward;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use DatabaseTransactions;

    private function newCampaign(bool $visible = true, array $overrides = []): Campaign
    {
        $campaign = Campaign::query()->create(array_merge([
            'slug' => 'slug-'.uniqid('', true),
            'title' => 'Public campaign',
            'description' => 'Description for API consumers.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ], $overrides));

        $campaign->forceFill(['visible' => $visible])->saveQuietly();

        return $campaign->fresh();
    }

    public function test_show_by_slug_returns_not_found_when_campaign_does_not_exist(): void
    {
        $this->getJson('api/campaigns/this-slug-does-not-exist-'.uniqid('', true))
            ->assertNotFound()
            ->assertJsonPath('message', 'Campaign not found');
    }

    public function test_show_by_slug_returns_not_found_when_campaign_is_not_visible(): void
    {
        $campaign = $this->newCampaign(visible: false);

        $this->getJson('api/campaigns/'.$campaign->slug)
            ->assertNotFound()
            ->assertJsonPath('message', 'Campaign not found');
    }

    public function test_show_by_slug_returns_milestones_ordered_by_amount_ascending(): void
    {
        $campaign = $this->newCampaign();

        CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Higher threshold',
            'description' => 'Second in DB insert order',
            'image_path' => null,
            'amount_cents' => 5_000,
        ]);
        CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Lower threshold',
            'description' => 'Should appear first in API',
            'image_path' => null,
            'amount_cents' => 500,
        ]);

        $response = $this->getJson('api/campaigns/'.$campaign->slug);

        $response->assertOk();
        $response->assertJsonPath('milestones.0.amount_cents', 500);
        $response->assertJsonPath('milestones.1.amount_cents', 5_000);
    }

    public function test_show_by_slug_returns_only_paid_donations_newest_first(): void
    {
        $campaign = $this->newCampaign();

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => null,
            'payment_id' => 'pay-old',
            'amount_cents' => 100,
            'name' => 'Older paid',
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $this->travel(2)->seconds();

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => null,
            'payment_id' => 'pay-new',
            'amount_cents' => 250,
            'name' => 'Newer paid',
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => null,
            'payment_id' => null,
            'amount_cents' => 9_999,
            'name' => 'Pending only',
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);

        $response = $this->getJson('api/campaigns/'.$campaign->slug);

        $response->assertOk();
        $response->assertJsonCount(2, 'donations');
        $response->assertJsonPath('donations.0.status', 'paid');
        $response->assertJsonPath('donations.0.amount_cents', 250);
        $response->assertJsonPath('donations.1.status', 'paid');
        $response->assertJsonPath('donations.1.amount_cents', 100);
        $response->assertJsonPath('total_donated', 350);
    }

    public function test_show_by_slug_returns_only_active_rewards(): void
    {
        $campaign = $this->newCampaign();

        CampaignReward::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Inactive perk',
            'description' => 'Hidden from public API',
            'donation_amount_cents' => 500,
            'quantity_available' => null,
            'is_active' => false,
        ]);

        CampaignReward::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Active perk',
            'description' => 'Shown to donors',
            'donation_amount_cents' => 1_000,
            'quantity_available' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('api/campaigns/'.$campaign->slug);

        $response->assertOk();
        $response->assertJsonCount(1, 'rewards');
        $response->assertJsonPath('rewards.0.title', 'Active perk');
        $response->assertJsonPath('rewards.0.is_active', true);
    }
}
