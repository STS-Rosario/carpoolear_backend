<?php

namespace Tests\Unit\Models;

use STS\Models\Badge;
use STS\Models\User;
use STS\Models\UserBadge;
use Tests\TestCase;

class UserBadgeTest extends TestCase
{
    private function makeBadge(): Badge
    {
        return Badge::query()->create([
            'title' => 'Pivot badge',
            'slug' => 'pivot-'.uniqid('', true),
            'rules' => ['type' => 'monthly_donor'],
            'visible' => true,
        ]);
    }

    public function test_pivot_row_resolves_user_and_badge_relations(): void
    {
        $user = User::factory()->create();
        $badge = $this->makeBadge();
        $awarded = now()->subHour();

        $user->badges()->attach($badge->id, ['awarded_at' => $awarded]);

        $pivot = UserBadge::query()
            ->where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->first();

        $this->assertInstanceOf(UserBadge::class, $pivot);
        $this->assertTrue($pivot->user->is($user));
        $this->assertTrue($pivot->badge->is($badge));
    }

    public function test_awarded_at_casts_to_carbon(): void
    {
        $user = User::factory()->create();
        $badge = $this->makeBadge();
        $user->badges()->attach($badge->id, ['awarded_at' => '2024-06-15 12:00:00']);

        $pivot = UserBadge::query()
            ->where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->first();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $pivot->awarded_at);
        $this->assertSame('2024-06-15', $pivot->awarded_at->toDateString());
    }

    public function test_user_badges_relationship_uses_user_badge_pivot_class(): void
    {
        $user = User::factory()->create();
        $badge = $this->makeBadge();
        $user->badges()->attach($badge->id, ['awarded_at' => now()]);

        $attached = $user->badges()->where('badges.id', $badge->id)->first();

        $this->assertNotNull($attached);
        $this->assertInstanceOf(UserBadge::class, $attached->pivot);
    }
}
