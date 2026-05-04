<?php

namespace Tests\Unit\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function test_fillable_lists_reward_columns(): void
    {
        $expected = [
            'campaign_id',
            'title',
            'description',
            'donation_amount_cents',
            'quantity_available',
            'is_active',
        ];

        $this->assertSame($expected, (new CampaignReward)->getFillable());
    }

    public function test_casts_include_numeric_and_active_columns(): void
    {
        $casts = (new CampaignReward)->getCasts();

        $this->assertSame('integer', $casts['donation_amount_cents']);
        $this->assertSame('integer', $casts['quantity_available']);
        $this->assertSame('boolean', $casts['is_active']);
    }

    public function test_appends_list_accessor_names_for_array_output(): void
    {
        $expected = [
            'donation_amount',
            'is_sold_out',
            'quantity_remaining',
        ];

        $this->assertSame($expected, (new CampaignReward)->getAppends());
    }

    public function test_to_array_includes_each_appended_accessor(): void
    {
        $reward = $this->makeReward($this->makeCampaign(), [
            'donation_amount_cents' => 500,
            'quantity_available' => 1,
        ]);

        $array = $reward->fresh()->toArray();

        foreach ((new CampaignReward)->getAppends() as $key) {
            $this->assertArrayHasKey($key, $array, "Appended attribute {$key} must appear in toArray().");
        }
    }

    public function test_mass_assignment_persists_all_fillable_columns(): void
    {
        $campaign = $this->makeCampaign();
        $payload = [
            'campaign_id' => $campaign->id,
            'title' => 'Tier A',
            'description' => 'Full payload row',
            'donation_amount_cents' => 2_500,
            'quantity_available' => 10,
            'is_active' => false,
        ];

        $this->assertEqualsCanonicalizing(
            (new CampaignReward)->getFillable(),
            array_keys($payload),
            'Payload must exercise every fillable key exactly once.'
        );

        $reward = CampaignReward::query()->create($payload);
        $row = $reward->fresh();

        $this->assertSame($campaign->id, $row->campaign_id);
        $this->assertSame('Tier A', $row->title);
        $this->assertSame('Full payload row', $row->description);
        $this->assertSame(2_500, $row->donation_amount_cents);
        $this->assertSame(10, $row->quantity_available);
        $this->assertFalse($row->is_active);
    }

    public function test_campaign_relation_is_belongs_to_campaign(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new CampaignReward)->campaign());
    }

    public function test_donations_relation_is_has_many_donations(): void
    {
        $this->assertInstanceOf(HasMany::class, (new CampaignReward)->donations());
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

    public function test_quantity_remaining_stays_zero_when_paid_donations_exceed_capacity(): void
    {
        // Mutation intent: `DecrementInteger` on `max(0, …)` (~64) — e.g. `max(-1, available - sold)` returns -1 when oversold by one.
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

        foreach (['p1', 'p2', 'p3'] as $paymentId) {
            CampaignDonation::query()->create(array_merge($base, [
                'status' => 'paid',
                'payment_id' => $paymentId,
            ]));
        }

        $this->assertSame(0, $reward->fresh()->quantity_remaining);
    }
}
