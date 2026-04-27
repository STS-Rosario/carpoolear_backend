<?php

namespace Tests\Unit\Models;

use Carbon\CarbonInterface;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignMilestone;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    private function makeCampaign(array $overrides = []): Campaign
    {
        $slug = 'camp-'.uniqid('', true);

        return Campaign::query()->create(array_merge([
            'slug' => $slug,
            'title' => 'Test campaign',
            'description' => 'Campaign description for tests.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ], $overrides));
    }

    public function test_route_key_is_slug(): void
    {
        $campaign = $this->makeCampaign(['slug' => 'unique-slug-'.uniqid()]);

        $this->assertSame('slug', $campaign->getRouteKeyName());
        $this->assertSame($campaign->slug, $campaign->getRouteKey());
    }

    public function test_date_and_visible_casts(): void
    {
        $campaign = $this->makeCampaign([
            'start_date' => '2025-01-15',
            'end_date' => '2025-12-31',
        ]);

        $campaign = $campaign->fresh();
        $this->assertInstanceOf(CarbonInterface::class, $campaign->start_date);
        $this->assertInstanceOf(CarbonInterface::class, $campaign->end_date);

        $campaign->forceFill(['visible' => false])->saveQuietly();
        $this->assertFalse($campaign->fresh()->visible);
    }

    public function test_milestones_and_donations_relations(): void
    {
        $campaign = $this->makeCampaign();

        CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'M1',
            'description' => 'First',
            'image_path' => null,
            'amount_cents' => 1_000,
        ]);

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => null,
            'amount_cents' => 100,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $this->assertSame(1, $campaign->milestones()->count());
        $this->assertSame(1, $campaign->donations()->count());
    }

    public function test_total_donated_accessor_sums_only_paid_cents(): void
    {
        $campaign = $this->makeCampaign();

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p1',
            'amount_cents' => 400,
            'user_id' => null,
            'status' => 'paid',
        ]);
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p2',
            'amount_cents' => 999,
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->assertSame(400, $campaign->fresh()->total_donated);
    }

    public function test_next_milestone_is_smallest_threshold_above_total_donated(): void
    {
        $campaign = $this->makeCampaign();

        CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Low',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 500,
        ]);
        CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'High',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 2_000,
        ]);

        $campaign = $campaign->fresh();
        $this->assertSame(500, $campaign->next_milestone->amount_cents);

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'paid1',
            'amount_cents' => 600,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $campaign = $campaign->fresh();
        $this->assertSame(2_000, $campaign->next_milestone->amount_cents);
    }
}
