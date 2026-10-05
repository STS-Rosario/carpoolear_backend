<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use STS\Models\Badge;
use STS\Models\DonationSubscription;
use STS\Models\User;
use STS\Services\ClubCarpoolearMembershipService;
use Tests\TestCase;

class ClubCarpoolearMembershipServiceTest extends TestCase
{
    private ClubCarpoolearMembershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClubCarpoolearMembershipService::class);
    }

    public function test_is_active_member_when_monthly_donate_is_true(): void
    {
        $user = User::factory()->create(['monthly_donate' => true]);

        $this->assertTrue($this->service->isActiveMember($user));
    }

    public function test_is_active_member_when_authorized_subscription_exists(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        DonationSubscription::create([
            'user_id' => $user->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
        ]);

        $this->assertTrue($this->service->isActiveMember($user->fresh()));
    }

    public function test_is_not_active_member_when_subscription_pending(): void
    {
        $user = User::factory()->create(['monthly_donate' => false]);
        DonationSubscription::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'transaction_amount_cents' => 500000,
        ]);

        $this->assertFalse($this->service->isActiveMember($user->fresh()));
    }

    public function test_apply_authorized_sets_joined_at_and_awards_club_badge(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $badge = Badge::query()->firstOrCreate(
            ['slug' => ClubCarpoolearMembershipService::BADGE_SLUG],
            [
                'title' => 'Club Carpoolear',
                'description' => 'Miembro del Club Carpoolear',
                'image_path' => 'badges/club-carpoolear.png',
                'rules' => ['type' => 'club_carpoolear'],
                'visible' => true,
            ]
        );

        $user = User::factory()->create([
            'monthly_donate' => false,
            'club_carpoolear_joined_at' => null,
        ]);

        $this->service->applyAuthorizedMembership($user);

        $user->refresh();
        $this->assertNotNull($user->club_carpoolear_joined_at);
        $this->assertTrue($user->badges->contains($badge->id));

        Carbon::setTestNow();
    }

    public function test_apply_authorized_resets_joined_at_on_rejoin(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        Badge::query()->firstOrCreate(
            ['slug' => ClubCarpoolearMembershipService::BADGE_SLUG],
            [
                'title' => 'Club Carpoolear',
                'rules' => ['type' => 'club_carpoolear'],
                'visible' => true,
            ]
        );

        $user = User::factory()->create([
            'club_carpoolear_joined_at' => Carbon::parse('2020-01-01'),
        ]);

        $this->service->applyAuthorizedMembership($user);

        $this->assertSame(
            '2026-07-01 12:00:00',
            $user->fresh()->club_carpoolear_joined_at->toDateTimeString()
        );

        Carbon::setTestNow();
    }

    public function test_apply_cancelled_keeps_joined_at_sets_left_at_and_removes_club_badge(): void
    {
        Carbon::setTestNow('2026-08-20 15:00:00');

        $badge = Badge::query()->firstOrCreate(
            ['slug' => ClubCarpoolearMembershipService::BADGE_SLUG],
            [
                'title' => 'Club Carpoolear',
                'rules' => ['type' => 'club_carpoolear'],
                'visible' => true,
            ]
        );

        $joinedAt = Carbon::parse('2026-01-10 09:00:00');
        $user = User::factory()->create([
            'club_carpoolear_joined_at' => $joinedAt,
            'club_carpoolear_left_at' => null,
        ]);
        $user->badges()->attach($badge->id, ['awarded_at' => now()]);

        $this->service->applyCancelledMembership($user);

        $user->refresh();
        $this->assertSame('2026-01-10 09:00:00', $user->club_carpoolear_joined_at->toDateTimeString());
        $this->assertSame('2026-08-20 15:00:00', $user->club_carpoolear_left_at->toDateTimeString());
        $this->assertFalse($user->badges->contains($badge->id));

        Carbon::setTestNow();
    }

    public function test_apply_authorized_clears_left_at_on_rejoin(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');

        Badge::query()->firstOrCreate(
            ['slug' => ClubCarpoolearMembershipService::BADGE_SLUG],
            [
                'title' => 'Club Carpoolear',
                'rules' => ['type' => 'club_carpoolear'],
                'visible' => true,
            ]
        );

        $user = User::factory()->create([
            'club_carpoolear_joined_at' => Carbon::parse('2026-01-10 09:00:00'),
            'club_carpoolear_left_at' => Carbon::parse('2026-08-20 15:00:00'),
        ]);

        $this->service->applyAuthorizedMembership($user);

        $user->refresh();
        $this->assertSame('2026-09-01 08:00:00', $user->club_carpoolear_joined_at->toDateTimeString());
        $this->assertNull($user->club_carpoolear_left_at);

        Carbon::setTestNow();
    }

    public function test_apply_cancelled_resets_welcome_shown_so_rejoin_can_see_welcome(): void
    {
        Badge::query()->firstOrCreate(
            ['slug' => ClubCarpoolearMembershipService::BADGE_SLUG],
            [
                'title' => 'Club Carpoolear',
                'rules' => ['type' => 'club_carpoolear'],
                'visible' => true,
            ]
        );

        $user = User::factory()->create([
            'club_carpoolear_joined_at' => now(),
        ]);
        $user->forceFill(['club_carpoolear_welcome_shown' => true])->save();

        $this->service->applyCancelledMembership($user);

        $this->assertFalse($user->fresh()->club_carpoolear_welcome_shown);
    }

    public function test_mark_welcome_shown_sets_flag_true(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['club_carpoolear_welcome_shown' => false])->save();

        $this->service->markWelcomeShown($user);

        $this->assertTrue($user->fresh()->club_carpoolear_welcome_shown);
    }
}
