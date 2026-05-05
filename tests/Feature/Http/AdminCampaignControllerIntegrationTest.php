<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use STS\Http\Middleware\UserAdmin;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignMilestone;
use STS\Models\CampaignReward;
use STS\Models\User;
use Tests\TestCase;

class AdminCampaignControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_orders_newest_first_and_exposes_total_donated_from_paid_rows(): void
    {
        $admin = $this->admin();
        $older = Campaign::create([
            'slug' => 'cmp-old-'.uniqid('', true),
            'title' => 'Older',
            'description' => 'D',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-old-'.uniqid('', true),
        ]);
        $newer = Campaign::create([
            'slug' => 'cmp-new-'.uniqid('', true),
            'title' => 'Newer',
            'description' => 'D',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-new-'.uniqid('', true),
        ]);

        DB::table('campaigns')->where('id', $older->id)->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        DB::table('campaigns')->where('id', $newer->id)->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        CampaignDonation::create([
            'campaign_id' => $newer->id,
            'payment_id' => 'p1',
            'amount_cents' => 1500,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);
        CampaignDonation::create([
            'campaign_id' => $newer->id,
            'payment_id' => null,
            'amount_cents' => 900,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);
        CampaignDonation::create([
            'campaign_id' => $newer->id,
            'payment_id' => 'p2',
            'amount_cents' => 500,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $rows = $this->getJson('api/admin/campaigns')->assertOk()->json();
        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(2, count($rows));

        $ids = array_column($rows, 'id');
        $newerPos = array_search($newer->id, $ids, true);
        $olderPos = array_search($older->id, $ids, true);
        $this->assertNotFalse($newerPos);
        $this->assertNotFalse($olderPos);
        $this->assertLessThan($olderPos, $newerPos);

        $newerPayload = collect($rows)->firstWhere('id', $newer->id);
        $this->assertNotNull($newerPayload);
        $this->assertSame(2000, (int) ($newerPayload['total_donated'] ?? 0));

        $olderPayload = collect($rows)->firstWhere('id', $older->id);
        $this->assertNotNull($olderPayload);
        $this->assertSame(0, (int) ($olderPayload['total_donated'] ?? -1));
    }

    public function test_store_generates_slug_from_title_when_slug_omitted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/campaigns', [
            'title' => 'Summer Fund Drive',
            'description' => 'Help us ship features.',
            'start_date' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('slug', 'summer-fund-drive')
            ->assertJsonPath('title', 'Summer Fund Drive');

        $this->assertDatabaseHas('campaigns', [
            'slug' => 'summer-fund-drive',
            'title' => 'Summer Fund Drive',
        ]);
    }

    public function test_store_respects_explicit_slug_and_rejects_invalid_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $slug = 'fixed-slug-'.uniqid('', true);
        $this->postJson('api/admin/campaigns', [
            'title' => 'Any Title',
            'description' => 'Body',
            'start_date' => now()->toDateString(),
            'slug' => $slug,
            'payment_slug' => 'pay-'.uniqid('', true),
        ])
            ->assertCreated()
            ->assertJsonPath('slug', $slug);

        $this->postJson('api/admin/campaigns', [
            'description' => 'Missing title',
        ])->assertUnprocessable();
    }

    public function test_show_loads_paid_donations_active_rewards_and_milestones(): void
    {
        $admin = $this->admin();
        $campaign = Campaign::create([
            'slug' => 'cmp-show-'.uniqid('', true),
            'title' => 'Show case',
            'description' => 'D',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-'.uniqid('', true),
        ]);

        CampaignMilestone::create([
            'campaign_id' => $campaign->id,
            'title' => 'M1',
            'description' => 'M',
            'image_path' => null,
            'amount_cents' => 10_000,
        ]);

        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p',
            'amount_cents' => 100,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);
        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'payment_id' => null,
            'amount_cents' => 200,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'pending',
        ]);

        CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Active perk',
            'description' => 'A',
            'donation_amount_cents' => 500,
            'quantity_available' => null,
            'is_active' => true,
        ]);
        CampaignReward::create([
            'campaign_id' => $campaign->id,
            'title' => 'Inactive perk',
            'description' => 'B',
            'donation_amount_cents' => 600,
            'quantity_available' => null,
            'is_active' => false,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/campaigns/'.$campaign->slug)->assertOk()->json();

        $this->assertCount(1, $data['milestones']);
        $this->assertCount(1, $data['donations']);
        $this->assertSame('paid', $data['donations'][0]['status']);
        $this->assertCount(1, $data['rewards']);
        $this->assertSame('Active perk', $data['rewards'][0]['title']);
        $this->assertSame(100, (int) $data['total_donated']);
    }

    public function test_update_regenerates_slug_when_title_changes(): void
    {
        $admin = $this->admin();
        $campaign = Campaign::create([
            'slug' => 'old-slug-'.uniqid('', true),
            'title' => 'Old title',
            'description' => 'D',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-'.uniqid('', true),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/campaigns/'.$campaign->slug, [
            'title' => 'Renamed campaign headline',
        ])
            ->assertOk()
            ->assertJsonPath('title', 'Renamed campaign headline')
            ->assertJsonPath('slug', 'renamed-campaign-headline');
    }

    public function test_destroy_returns_no_content(): void
    {
        $admin = $this->admin();
        $campaign = Campaign::create([
            'slug' => 'to-delete-'.uniqid('', true),
            'title' => 'T',
            'description' => 'D',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-'.uniqid('', true),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->deleteJson('api/admin/campaigns/'.$campaign->slug)
            ->assertNoContent();

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }
}
