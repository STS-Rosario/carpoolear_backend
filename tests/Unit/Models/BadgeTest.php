<?php

namespace Tests\Unit\Models;

use Illuminate\Support\Carbon;
use STS\Models\Badge;
use STS\Models\User;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    public function test_fillable_contains_expected_mass_assignable_attributes(): void
    {
        $this->assertSame([
            'title',
            'slug',
            'description',
            'image_path',
            'rules',
            'visible',
        ], (new Badge)->getFillable());
    }

    public function test_rules_and_visible_are_cast(): void
    {
        $badge = Badge::query()->create([
            'title' => 'Cast badge',
            'slug' => 'cast-'.uniqid('', true),
            'description' => null,
            'image_path' => null,
            'rules' => ['type' => 'carpoolear_member'],
            'visible' => true,
        ]);

        $badge = $badge->fresh();
        $this->assertIsArray($badge->rules);
        $this->assertSame('carpoolear_member', $badge->rules['type']);
        $this->assertTrue($badge->visible);

        $badge->forceFill(['visible' => false])->saveQuietly();
        $this->assertFalse($badge->fresh()->visible);
    }

    public function test_mass_assignment_persists_all_fillable_fields(): void
    {
        $badge = Badge::query()->create([
            'title' => 'Driver verified',
            'slug' => 'driver-verified-'.uniqid('', true),
            'description' => 'Granted after verification',
            'image_path' => '/img/badges/driver-verified.png',
            'rules' => ['type' => 'driver_verified', 'minTrips' => 1],
            'visible' => false,
        ])->fresh();

        $this->assertSame('Driver verified', $badge->title);
        $this->assertSame('Granted after verification', $badge->description);
        $this->assertSame('/img/badges/driver-verified.png', $badge->image_path);
        $this->assertSame('driver_verified', $badge->rules['type']);
        $this->assertSame(1, $badge->rules['minTrips']);
        $this->assertFalse($badge->visible);
    }

    public function test_users_relation_attaches_with_awarded_at_pivot(): void
    {
        $badge = Badge::query()->create([
            'title' => 'Shared',
            'slug' => 'shared-'.uniqid('', true),
            'rules' => ['type' => 'monthly_donor'],
            'visible' => true,
        ]);

        $user = User::factory()->create();
        $awarded = now()->subMinutes(30);

        $badge->users()->attach($user->id, ['awarded_at' => $awarded]);

        $this->assertTrue($badge->users()->whereKey($user->id)->exists());

        $related = $badge->users()->whereKey($user->id)->first();
        $this->assertNotNull($related->pivot);
        $this->assertNotNull($related->pivot->awarded_at);
        $this->assertLessThanOrEqual(
            2,
            abs(Carbon::parse($related->pivot->awarded_at)->diffInSeconds($awarded)),
            'Pivot awarded_at should match attach value within clock skew'
        );
    }

    public function test_multiple_users_can_hold_same_badge(): void
    {
        $badge = Badge::query()->create([
            'title' => 'Multi',
            'slug' => 'multi-'.uniqid('', true),
            'rules' => ['type' => 'monthly_donor'],
            'visible' => true,
        ]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $badge->users()->attach($u1->id, ['awarded_at' => now()]);
        $badge->users()->attach($u2->id, ['awarded_at' => now()]);

        $this->assertSame(2, $badge->users()->count());
    }
}
