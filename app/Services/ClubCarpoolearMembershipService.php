<?php

namespace STS\Services;

use Illuminate\Support\Collection;
use STS\Models\Badge;
use STS\Models\DonationSubscription;
use STS\Models\User;

class ClubCarpoolearMembershipService
{
    public const BADGE_SLUG = 'club-carpoolear';

    public function isActiveMember(User $user): bool
    {
        if ($user->monthly_donate) {
            return true;
        }

        return DonationSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'authorized')
            ->exists();
    }

    public function applyAuthorizedMembership(User $user): void
    {
        $user->club_carpoolear_joined_at = now();
        $user->save();

        $this->syncClubBadge($user, true);
    }

    public function applyCancelledMembership(User $user): void
    {
        $user->club_carpoolear_joined_at = null;
        $user->save();

        $this->syncClubBadge($user, false);
    }

    public function shouldShowMembershipPublicly(User $user): bool
    {
        return $this->isActiveMember($user) && (bool) $user->show_club_carpoolear_membership;
    }

    /**
     * @param  Collection<int, Badge>  $badges
     * @return Collection<int, Badge>
     */
    public function filterVisibleBadges(User $user, Collection $badges): Collection
    {
        if ($this->shouldShowMembershipPublicly($user)) {
            return $badges;
        }

        $clubBadgeId = Badge::query()->where('slug', self::BADGE_SLUG)->value('id');
        if (! $clubBadgeId) {
            return $badges;
        }

        return $badges->reject(fn (Badge $badge) => (int) $badge->id === (int) $clubBadgeId)->values();
    }

    private function syncClubBadge(User $user, bool $shouldHaveBadge): void
    {
        $badge = Badge::query()->where('slug', self::BADGE_SLUG)->first();
        if (! $badge) {
            return;
        }

        $user->loadMissing('badges');

        if ($shouldHaveBadge) {
            if (! $user->badges->contains($badge->id)) {
                $user->badges()->attach($badge->id, ['awarded_at' => now()]);
            }

            return;
        }

        $user->badges()->detach($badge->id);
    }
}
