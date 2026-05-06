<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\User;
use Tests\TestCase;

class AdminCampaignDonationControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    private function makeCampaign(): Campaign
    {
        return Campaign::create([
            'slug' => 'don-cmp-'.uniqid('', true),
            'title' => 'Donation campaign',
            'description' => 'For admin donation API tests.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-'.uniqid('', true),
        ]);
    }

    private function donationsBaseUrl(Campaign $campaign): string
    {
        return 'api/admin/campaigns/'.$campaign->slug.'/donations';
    }

    public function test_index_returns_donations_with_user_embedded(): void
    {
        $admin = $this->admin();
        $campaign = $this->makeCampaign();
        $donor = User::factory()->create(['name' => 'Donor Person']);

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'pay-idx-'.uniqid(),
            'amount_cents' => 500,
            'name' => 'Public name',
            'comment' => 'Thanks',
            'user_id' => $donor->id,
            'status' => 'paid',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $list = $this->getJson($this->donationsBaseUrl($campaign))->assertOk()->json();
        $this->assertIsArray($list);
        $this->assertNotEmpty($list);
        $first = $list[0];
        $this->assertSame(500, (int) ($first['amount_cents'] ?? 0));
        $this->assertArrayHasKey('user', $first);
        $this->assertSame('Donor Person', $first['user']['name'] ?? null);
    }

    public function test_store_creates_donation_and_returns_created_status(): void
    {
        $admin = $this->admin();
        $campaign = $this->makeCampaign();
        $donor = User::factory()->create();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson($this->donationsBaseUrl($campaign), [
            'payment_id' => 'pref-'.uniqid(),
            'amount_cents' => 1200,
            'name' => 'Anonymous',
            'comment' => 'Go team',
            'user_id' => $donor->id,
            'status' => 'pending',
        ])->assertCreated();

        $id = (int) $response->json('id');
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('campaign_donations', [
            'id' => $id,
            'campaign_id' => $campaign->id,
            'amount_cents' => 1200,
            'status' => 'pending',
            'user_id' => $donor->id,
        ]);
    }

    public function test_show_loads_user_and_campaign(): void
    {
        $admin = $this->admin();
        $campaign = $this->makeCampaign();
        $donation = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => null,
            'amount_cents' => 800,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'failed',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson($this->donationsBaseUrl($campaign).'/'.$donation->id)
            ->assertOk()
            ->json();

        $this->assertSame($donation->id, (int) ($data['id'] ?? 0));
        $this->assertSame('failed', $data['status']);
        $this->assertArrayHasKey('campaign', $data);
        $this->assertSame($campaign->slug, $data['campaign']['slug'] ?? null);
    }

    public function test_update_persists_changed_fields(): void
    {
        $admin = $this->admin();
        $campaign = $this->makeCampaign();
        $donation = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'old',
            'amount_cents' => 100,
            'name' => 'Old',
            'comment' => 'Old comment',
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson($this->donationsBaseUrl($campaign).'/'.$donation->id, [
            'amount_cents' => 250,
            'status' => 'paid',
            'comment' => 'Paid offline',
        ])->assertOk()->assertJsonPath('amount_cents', 250)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('comment', 'Paid offline');

        $donation->refresh();
        $this->assertSame(250, (int) $donation->amount_cents);
        $this->assertSame('paid', $donation->status);
    }

    public function test_show_returns_not_found_when_donation_belongs_to_another_campaign(): void
    {
        $admin = $this->admin();
        $campaignA = $this->makeCampaign();
        $campaignB = $this->makeCampaign();
        $donationOnB = CampaignDonation::query()->create([
            'campaign_id' => $campaignB->id,
            'payment_id' => null,
            'amount_cents' => 10,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson($this->donationsBaseUrl($campaignA).'/'.$donationOnB->id)
            ->assertNotFound();
    }

    public function test_destroy_removes_donation_and_returns_no_content(): void
    {
        $admin = $this->admin();
        $campaign = $this->makeCampaign();
        $donation = CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => null,
            'amount_cents' => 50,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->deleteJson($this->donationsBaseUrl($campaign).'/'.$donation->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('campaign_donations', ['id' => $donation->id]);
    }
}
