<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Console\Commands\AssignMsMjmsCampaignBadge;
use STS\Models\Badge;
use STS\Models\CampaignDonation;
use STS\Models\User;
use STS\Models\UserBadge;
use Tests\TestCase;

class AssignMsMjmsCampaignBadgeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedCampaign(int $id): void
    {
        DB::table('campaigns')->insert([
            'id' => $id,
            'slug' => 'campaign-'.$id,
            'title' => 'Campaign '.$id,
            'description' => 'Desc',
            'image_path' => null,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'payment_slug' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_dry_run_lists_donors_without_persisting_badge_or_assignments(): void
    {
        $this->seedCampaign(1);
        $donor = User::factory()->create();

        CampaignDonation::query()->create([
            'campaign_id' => 1,
            'campaign_reward_id' => null,
            'payment_id' => null,
            'amount_cents' => 5000,
            'name' => 'Donor',
            'comment' => null,
            'user_id' => $donor->id,
            'status' => 'paid',
        ]);

        $this->artisan('badges:assign-msmjms-campaign', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Would create badge')
            ->expectsOutputToContain('Found 1 unique donor(s)')
            ->expectsOutput('Dry run complete. Run without --dry-run to apply.')
            ->assertExitCode(0);

        $this->assertSame(0, Badge::query()->where('slug', 'aportante-msmjms')->count());
        $this->assertSame(0, UserBadge::query()->count());
    }

    public function test_handle_creates_badge_and_assigns_only_paid_campaign_one_donors(): void
    {
        $this->seedCampaign(1);
        $this->seedCampaign(2);
        $paidDonor = User::factory()->create();
        $otherCampaignDonor = User::factory()->create();
        $failedDonor = User::factory()->create();

        CampaignDonation::query()->create([
            'campaign_id' => 1,
            'campaign_reward_id' => null,
            'payment_id' => null,
            'amount_cents' => 3000,
            'name' => 'Paid Donor',
            'comment' => null,
            'user_id' => $paidDonor->id,
            'status' => 'paid',
        ]);
        CampaignDonation::query()->create([
            'campaign_id' => 2,
            'campaign_reward_id' => null,
            'payment_id' => null,
            'amount_cents' => 3000,
            'name' => 'Other Campaign',
            'comment' => null,
            'user_id' => $otherCampaignDonor->id,
            'status' => 'paid',
        ]);
        CampaignDonation::query()->create([
            'campaign_id' => 1,
            'campaign_reward_id' => null,
            'payment_id' => null,
            'amount_cents' => 3000,
            'name' => 'Failed Donor',
            'comment' => null,
            'user_id' => $failedDonor->id,
            'status' => 'failed',
        ]);

        $this->artisan('badges:assign-msmjms-campaign')
            ->expectsOutputToContain('Badge created')
            ->expectsOutputToContain('Found 1 unique donor(s)')
            ->expectsOutputToContain('Assigned badge to 1 user(s).')
            ->assertExitCode(0);

        $badge = Badge::query()->where('slug', 'aportante-msmjms')->first();
        $this->assertNotNull($badge);
        $this->assertSame('Aporté a la campaña +S+J+S', $badge->title);

        $this->assertSame(1, UserBadge::query()->where('badge_id', $badge->id)->count());
        $this->assertSame(1, UserBadge::query()->where('badge_id', $badge->id)->where('user_id', $paidDonor->id)->count());
        $this->assertSame(0, UserBadge::query()->where('badge_id', $badge->id)->where('user_id', $otherCampaignDonor->id)->count());
        $this->assertSame(0, UserBadge::query()->where('badge_id', $badge->id)->where('user_id', $failedDonor->id)->count());
    }

    public function test_handle_reports_zero_donors_when_no_paid_donations_exist(): void
    {
        $this->seedCampaign(1);

        $this->artisan('badges:assign-msmjms-campaign')
            ->expectsOutputToContain('Found 0 unique donor(s) with paid donations for campaign 1.')
            ->assertExitCode(0);
    }

    public function test_handle_skips_insert_when_every_donor_already_has_the_badge(): void
    {
        $this->seedCampaign(1);
        $donor = User::factory()->create();

        CampaignDonation::query()->create([
            'campaign_id' => 1,
            'campaign_reward_id' => null,
            'payment_id' => null,
            'amount_cents' => 1000,
            'name' => 'Donor',
            'comment' => null,
            'user_id' => $donor->id,
            'status' => 'paid',
        ]);

        $badge = Badge::query()->create([
            'title' => 'Aporté a la campaña +S+J+S',
            'slug' => 'aportante-msmjms',
            'description' => 'Yo aporté para la campaña +Seguro +Justo +Simple',
            'image_path' => 'badges/msmjms.jpg',
            'rules' => ['campaignId' => 1],
            'visible' => true,
        ]);

        UserBadge::query()->create([
            'user_id' => $donor->id,
            'badge_id' => $badge->id,
            'awarded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('badges:assign-msmjms-campaign')
            ->expectsOutputToContain('Badge already exists')
            ->expectsOutputToContain('1 user(s) already have this badge.')
            ->expectsOutputToContain('No new user_badges to assign.')
            ->assertExitCode(0);

        $this->assertSame(1, UserBadge::query()->where('badge_id', $badge->id)->count());
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new AssignMsMjmsCampaignBadge;

        $this->assertSame('badges:assign-msmjms-campaign', $command->getName());
        $this->assertStringContainsString('Create the +S+J+S campaign badge', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }
}
