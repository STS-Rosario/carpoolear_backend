<?php

namespace Tests\Unit\Models;

use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignMilestone;
use Tests\TestCase;

class CampaignMilestoneTest extends TestCase
{
    public function test_fillable_contains_expected_mass_assignable_attributes(): void
    {
        $this->assertSame([
            'campaign_id',
            'title',
            'description',
            'image_path',
            'amount_cents',
        ], (new CampaignMilestone)->getFillable());
    }

    private function makeCampaign(): Campaign
    {
        return Campaign::query()->create([
            'slug' => 'ms-'.uniqid('', true),
            'title' => 'Milestone campaign',
            'description' => 'For milestone tests.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ]);
    }

    public function test_belongs_to_campaign(): void
    {
        $campaign = $this->makeCampaign();
        $milestone = CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Goal',
            'description' => 'Desc',
            'image_path' => null,
            'amount_cents' => 500,
        ]);

        $this->assertTrue($milestone->campaign->is($campaign));
    }

    public function test_is_reached_when_paid_total_meets_or_exceeds_amount(): void
    {
        $campaign = $this->makeCampaign();
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p1',
            'amount_cents' => 500,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $milestone = CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Half K',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 500,
        ]);

        $this->assertTrue($milestone->fresh()->isReached());

        $higher = CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Above total',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 501,
        ]);

        $this->assertFalse($higher->fresh()->isReached());
    }

    public function test_progress_percentage_scales_and_caps_at_100(): void
    {
        $campaign = $this->makeCampaign();
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p1',
            'amount_cents' => 250,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $milestone = CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'K',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 1_000,
        ]);

        $milestone = $milestone->fresh();
        $this->assertSame(25, $milestone->progress_percentage);

        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p2',
            'amount_cents' => 2_000,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $this->assertSame(100, $milestone->fresh()->progress_percentage);
    }

    public function test_progress_percentage_is_zero_when_milestone_target_is_zero(): void
    {
        $campaign = $this->makeCampaign();
        $milestone = CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Zero target',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 0,
        ]);

        $this->assertSame(0, $milestone->fresh()->progress_percentage);
    }

    public function test_progress_percentage_returns_truncated_integer_value(): void
    {
        $campaign = $this->makeCampaign();
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'p-frac',
            'amount_cents' => 100,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $milestone = CampaignMilestone::query()->create([
            'campaign_id' => $campaign->id,
            'title' => 'Fractional progress',
            'description' => 'D',
            'image_path' => null,
            'amount_cents' => 333,
        ]);

        $fresh = $milestone->fresh();
        $this->assertSame(30, $fresh->progress_percentage);
        $this->assertIsInt($fresh->progress_percentage);
    }
}
