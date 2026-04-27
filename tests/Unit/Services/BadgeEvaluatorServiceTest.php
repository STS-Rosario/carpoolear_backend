<?php

namespace Tests\Unit\Services;

use Mockery;
use ReflectionMethod;
use STS\Models\Badge;
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
