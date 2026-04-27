<?php

namespace Tests\Unit\Services;

use Mockery;
use ReflectionMethod;
use STS\Models\Badge;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\Donation;
use STS\Models\User;
use STS\Services\BadgeEvaluatorService;
use Tests\TestCase;

class BadgeEvaluatorServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_evaluate_completes_when_no_badges_exist(): void
    {
        Badge::query()->delete();

        $user = User::factory()->create();

        (new BadgeEvaluatorService)->evaluate($user);

        $this->assertSame(0, $user->fresh()->badges()->count());
    }

    public function test_evaluate_does_not_award_badge_with_invalid_rules(): void
    {
        Badge::query()->delete();

        Badge::query()->create([
            'title' => 'Broken rules',
            'slug' => 'broken-'.uniqid('', true),
            'rules' => [],
            'visible' => true,
        ]);

        $user = User::factory()->create();

        (new BadgeEvaluatorService)->evaluate($user);

        $this->assertSame(0, $user->fresh()->badges()->count());
    }

    public function test_evaluate_awards_registration_duration_when_account_is_old_enough(): void
    {
        Badge::query()->delete();

        $badge = Badge::query()->create([
            'title' => 'Tenured',
            'slug' => 'tenured-'.uniqid('', true),
            'rules' => [
                'type' => 'registration_duration',
                'days' => 2,
            ],
            'visible' => true,
        ]);

        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertTrue($user->fresh()->badges()->where('badges.id', $badge->id)->exists());
    }

    public function test_evaluate_awards_donated_to_campaign_when_user_has_paid_donation(): void
    {
        Badge::query()->delete();

        $campaign = Campaign::query()->create([
            'slug' => 'camp-badge-'.uniqid('', true),
            'title' => 'Fundraiser',
            'description' => 'For badge test.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ]);

        $user = User::factory()->create();
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => 'mp-'.uniqid(),
            'amount_cents' => 500,
            'name' => null,
            'comment' => null,
            'user_id' => $user->id,
            'status' => 'paid',
        ]);

        $badge = Badge::query()->create([
            'title' => 'Campaign supporter',
            'slug' => 'camp-sup-'.uniqid('', true),
            'rules' => [
                'type' => 'donated_to_campaign',
                'campaign_id' => $campaign->id,
            ],
            'visible' => true,
        ]);

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertTrue($user->fresh()->badges()->where('badges.id', $badge->id)->exists());
    }

    public function test_evaluate_awards_total_donated_when_lifetime_ammount_meets_threshold(): void
    {
        Badge::query()->delete();

        $user = User::factory()->create();
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => now()->subMonths(3),
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 40,
        ]);
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => now()->subMonth(),
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 70,
        ]);

        $badge = Badge::query()->create([
            'title' => 'Generous',
            'slug' => 'total-don-'.uniqid('', true),
            'rules' => [
                'type' => 'total_donated',
                'amount' => 100,
            ],
            'visible' => true,
        ]);

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertTrue($user->fresh()->badges()->where('badges.id', $badge->id)->exists());
    }

    public function test_evaluate_does_not_award_total_donated_when_below_threshold(): void
    {
        Badge::query()->delete();

        $user = User::factory()->create();
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => now(),
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 30,
        ]);

        Badge::query()->create([
            'title' => 'Not yet',
            'slug' => 'total-don-low-'.uniqid('', true),
            'rules' => [
                'type' => 'total_donated',
                'amount' => 500,
            ],
            'visible' => true,
        ]);

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertSame(0, $user->fresh()->badges()->count());
    }

    public function test_evaluate_awards_monthly_donor_when_current_month_marked_donated(): void
    {
        Badge::query()->delete();

        $user = User::factory()->create();
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => now(),
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 10,
        ]);

        $badge = Badge::query()->create([
            'title' => 'Monthly',
            'slug' => 'monthly-'.uniqid('', true),
            'rules' => [
                'type' => 'monthly_donor',
            ],
            'visible' => true,
        ]);

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertTrue($user->fresh()->badges()->where('badges.id', $badge->id)->exists());
    }

    public function test_evaluate_does_not_award_monthly_donor_when_has_donated_false(): void
    {
        Badge::query()->delete();

        $user = User::factory()->create();
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => now(),
            'has_donated' => false,
            'has_denied' => false,
            'ammount' => 99,
        ]);

        Badge::query()->create([
            'title' => 'Not monthly',
            'slug' => 'monthly-no-'.uniqid('', true),
            'rules' => [
                'type' => 'monthly_donor',
            ],
            'visible' => true,
        ]);

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertSame(0, $user->fresh()->badges()->count());
    }

    public function test_evaluate_does_not_award_campaign_badge_for_pending_donation_only(): void
    {
        Badge::query()->delete();

        $campaign = Campaign::query()->create([
            'slug' => 'camp-pend-'.uniqid('', true),
            'title' => 'Pending fundraiser',
            'description' => 'For badge test.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => null,
        ]);

        $user = User::factory()->create();
        CampaignDonation::query()->create([
            'campaign_id' => $campaign->id,
            'payment_id' => null,
            'amount_cents' => 100,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        Badge::query()->create([
            'title' => 'Should not unlock',
            'slug' => 'camp-pend-badge-'.uniqid('', true),
            'rules' => [
                'type' => 'donated_to_campaign',
                'campaign_id' => $campaign->id,
            ],
            'visible' => true,
        ]);

        (new BadgeEvaluatorService)->evaluate($user->fresh());

        $this->assertSame(0, $user->fresh()->badges()->count());
    }

    public function test_evaluate_is_idempotent_for_same_badge(): void
    {
        Badge::query()->delete();

        Badge::query()->create([
            'title' => 'Tenured',
            'slug' => 'tenured-'.uniqid('', true),
            'rules' => [
                'type' => 'registration_duration',
                'days' => 1,
            ],
            'visible' => true,
        ]);

        $user = User::factory()->create();
        $user->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();
        $user = $user->fresh();

        $service = new BadgeEvaluatorService;
        $service->evaluate($user);
        $service->evaluate($user);

        $this->assertSame(1, $user->fresh()->badges()->count());
    }

    public function test_meets_conditions_returns_false_for_unknown_rule_type(): void
    {
        $service = new BadgeEvaluatorService;
        $method = new ReflectionMethod(BadgeEvaluatorService::class, 'meetsConditions');
        $method->setAccessible(true);

        $user = User::factory()->create();
        $badge = new Badge([
            'rules' => ['type' => 'not_a_real_type'],
        ]);

        $this->assertFalse($method->invoke($service, $user, $badge));
    }

    public function test_meets_conditions_carpoolear_member_for_team_ids(): void
    {
        $service = new BadgeEvaluatorService;
        $method = new ReflectionMethod(BadgeEvaluatorService::class, 'meetsConditions');
        $method->setAccessible(true);

        $badge = new Badge([
            'rules' => ['type' => 'carpoolear_member'],
        ]);

        $member = Mockery::mock(User::class);
        $member->shouldReceive('getAttribute')->with('id')->andReturn(3209);
        $this->assertTrue($method->invoke($service, $member, $badge));

        $other = Mockery::mock(User::class);
        $other->shouldReceive('getAttribute')->with('id')->andReturn(999_001);
        $this->assertFalse($method->invoke($service, $other, $badge));
    }
}
