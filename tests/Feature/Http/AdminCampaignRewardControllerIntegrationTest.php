<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignReward;
use STS\Models\User;
use Tests\TestCase;

class AdminCampaignRewardControllerIntegrationTest extends TestCase
{
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    private function makeCampaign(): Campaign
    {
        return Campaign::create([
            'slug' => 'adm-cmp-'.uniqid('', true),
            'title' => 'Admin Campaign',
            'description' => 'Description for admin campaign tests.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-'.uniqid('', true),
        ]);
    }

    private function rewardsIndexUrl(Campaign $campaign): string
    {
        return 'api/admin/campaigns/'.$campaign->slug.'/rewards';
    }

    private function rewardMemberUrl(Campaign $campaign, CampaignReward $reward): string
    {
        return 'api/admin/campaigns/'.$campaign->slug.'/rewards/'.$reward->id;
    }

    public function test_index_returns_rewards_with_paid_donations_count(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();
        $withPaid = CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Tier A',
            'description' => 'A',
            'donation_amount_cents' => 1000,
            'quantity_available' => null,
            'is_active' => true,
        ]);
        $noPaid = CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Tier B',
            'description' => 'B',
            'donation_amount_cents' => 500,
            'quantity_available' => null,
            'is_active' => true,
        ]);

        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $withPaid->id,
            'payment_id' => 'p1',
            'amount_cents' => 1000,
            'status' => 'paid',
        ]);
        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $withPaid->id,
            'payment_id' => 'p2',
            'amount_cents' => 1000,
            'status' => 'paid',
        ]);
        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $withPaid->id,
            'payment_id' => null,
            'amount_cents' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->getJson($this->rewardsIndexUrl($campaign));
        $response->assertOk();
        $rows = $response->json();
        $this->assertIsArray($rows);
        $byId = collect($rows)->keyBy('id');

        $this->assertSame(2, (int) $byId[$withPaid->id]['donations_count']);
        $this->assertSame(0, (int) $byId[$noPaid->id]['donations_count']);
    }

    public function test_store_creates_reward_and_returns_created_payload(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();

        $this->postJson($this->rewardsIndexUrl($campaign), [
            'title' => 'New reward',
            'description' => 'Full text',
            'donation_amount_cents' => 3200,
            'quantity_available' => 10,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'New reward')
            ->assertJsonPath('description', 'Full text')
            ->assertJsonPath('donation_amount_cents', 3200)
            ->assertJsonPath('quantity_available', 10)
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('campaign_id', $campaign->id)
            ->assertJsonMissingPath('error');

        $this->assertDatabaseHas('campaign_rewards', [
            'campaign_id' => $campaign->id,
            'title' => 'New reward',
            'donation_amount_cents' => 3200,
            'quantity_available' => 10,
        ]);
    }

    public function test_store_returns_unprocessable_when_validation_fails(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();

        $this->postJson($this->rewardsIndexUrl($campaign), [
            'description' => 'Only description',
        ])
            ->assertUnprocessable();

        $this->assertSame(0, CampaignReward::where('campaign_id', $campaign->id)->count());
    }

    public function test_show_returns_reward_with_paid_donation_count(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();
        $reward = CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Show me',
            'description' => 'D',
            'donation_amount_cents' => 1500,
            'quantity_available' => null,
            'is_active' => true,
        ]);
        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'payment_id' => 'x',
            'amount_cents' => 1500,
            'status' => 'paid',
        ]);

        $this->getJson($this->rewardMemberUrl($campaign, $reward))
            ->assertOk()
            ->assertJsonPath('id', $reward->id)
            ->assertJsonPath('donations_count', 1);
    }

    public function test_show_load_count_excludes_pending_donations(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();
        $reward = CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Mixed donations',
            'description' => 'D',
            'donation_amount_cents' => 1500,
            'quantity_available' => null,
            'is_active' => true,
        ]);

        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'payment_id' => 'paid-1',
            'amount_cents' => 1500,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);
        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'payment_id' => null,
            'amount_cents' => 1500,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->getJson($this->rewardMemberUrl($campaign, $reward))
            ->assertOk()
            ->assertJsonPath('donations_count', 1);
    }

    public function test_show_returns_not_found_when_reward_belongs_to_another_campaign(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaignA = $this->makeCampaign();
        $campaignB = $this->makeCampaign();
        $rewardOnB = CampaignReward::create([
            'campaign_id' => $campaignB->id,
            'title' => 'Other',
            'description' => 'X',
            'donation_amount_cents' => 100,
            'quantity_available' => null,
            'is_active' => true,
        ]);

        $this->getJson($this->rewardMemberUrl($campaignA, $rewardOnB))
            ->assertNotFound()
            ->assertExactJson(['error' => 'Reward does not belong to this campaign']);
    }

    public function test_update_persists_changes_for_matching_campaign(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();
        $reward = CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Old',
            'description' => 'Old desc',
            'donation_amount_cents' => 900,
            'quantity_available' => 5,
            'is_active' => true,
        ]);

        $this->putJson($this->rewardMemberUrl($campaign, $reward), [
            'title' => 'Renamed',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('id', $reward->id)
            ->assertJsonPath('campaign_id', $campaign->id)
            ->assertJsonPath('title', 'Renamed')
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('donation_amount_cents', 900);

        $this->assertDatabaseHas('campaign_rewards', [
            'id' => $reward->id,
            'title' => 'Renamed',
            'is_active' => false,
        ]);
    }

    public function test_update_returns_not_found_when_reward_belongs_to_another_campaign(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaignA = $this->makeCampaign();
        $campaignB = $this->makeCampaign();
        $rewardOnB = CampaignReward::create([
            'campaign_id' => $campaignB->id,
            'title' => 'B-only',
            'description' => 'X',
            'donation_amount_cents' => 200,
            'quantity_available' => null,
            'is_active' => true,
        ]);

        $this->putJson($this->rewardMemberUrl($campaignA, $rewardOnB), ['title' => 'Hijack'])
            ->assertNotFound()
            ->assertExactJson(['error' => 'Reward does not belong to this campaign']);

        $this->assertDatabaseHas('campaign_rewards', [
            'id' => $rewardOnB->id,
            'title' => 'B-only',
        ]);
    }

    public function test_destroy_returns_no_content_and_deletes_reward(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaign = $this->makeCampaign();
        $reward = CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'To delete',
            'description' => 'X',
            'donation_amount_cents' => 100,
            'quantity_available' => null,
            'is_active' => true,
        ]);

        $this->deleteJson($this->rewardMemberUrl($campaign, $reward))
            ->assertNoContent();

        $this->assertDatabaseMissing('campaign_rewards', ['id' => $reward->id]);
    }

    public function test_destroy_returns_not_found_when_reward_belongs_to_another_campaign(): void
    {
        $this->actingAs($this->adminUser(), 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $campaignA = $this->makeCampaign();
        $campaignB = $this->makeCampaign();
        $rewardOnB = CampaignReward::create([
            'campaign_id' => $campaignB->id,
            'title' => 'Keep',
            'description' => 'X',
            'donation_amount_cents' => 100,
            'quantity_available' => null,
            'is_active' => true,
        ]);

        $this->deleteJson($this->rewardMemberUrl($campaignA, $rewardOnB))
            ->assertNotFound()
            ->assertExactJson(['error' => 'Reward does not belong to this campaign']);

        $this->assertDatabaseHas('campaign_rewards', ['id' => $rewardOnB->id]);
    }
}
