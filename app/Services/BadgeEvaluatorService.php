<?php

namespace STS\Services;

use STS\Models\User;
use STS\Models\Badge;
use Illuminate\Support\Facades\Log;

class BadgeEvaluatorService
{
    /**
     * Evaluate all badges for a user and award any that are earned.
     *
     * @param User $user The user to evaluate badges for
     * @return void
     */
    public function evaluate(User $user): void
    {
        $badges = Badge::all();

        foreach ($badges as $badge) {
            try {
                if ($this->meetsConditions($user, $badge) && !$user->badges->contains($badge->id)) {
                    $user->badges()->attach($badge->id, ['awarded_at' => now()]);
                    Log::info('Badge awarded', [
                        'user_id' => $user->id,
                        'badge_id' => $badge->id,
                        'badge_title' => $badge->title
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error evaluating badge', [
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Check if a user meets the conditions for a badge.
     *
     * @param User $user The user to check
     * @param Badge $badge The badge to check conditions for
     * @return bool Whether the user meets the badge conditions
     * @throws \InvalidArgumentException If the badge rules are invalid
     */
    protected function meetsConditions(User $user, Badge $badge): bool
    {
        $rules = $badge->rules ?? [];
        
        if (empty($rules) || !isset($rules['type'])) {
            Log::warning('Badge has no rules or invalid rules', [
                'badge_id' => $badge->id,
                'rules' => $rules
            ]);
            return false;
        }

        return match ($rules['type']) {
            'registration_duration' => $this->checkRegistrationDuration($user, $rules),
            'donated_to_campaign' => $this->checkCampaignDonation($user, $rules),
            'total_donated' => $this->checkTotalDonations($user, $rules),
            'monthly_donor' => $this->checkMonthlyDonor($user),
            'carpoolear_member' => $this->checkCarpoolearMember($user),
            default => false,
        };
    }

    /**
     * Check if user has been registered for the required duration.
     */
    protected function checkRegistrationDuration(User $user, array $rules): bool
    {
        if (!isset($rules['days'])) {
            throw new \InvalidArgumentException('Registration duration badge requires days parameter');
        }
        return (int) now()->diffInDays($user->created_at) >= $rules['days'];
    }

    /**
     * Check if user has donated to a specific campaign.
     */
    protected function checkCampaignDonation(User $user, array $rules): bool
    {
        if (!isset($rules['campaign_id'])) {
            throw new \InvalidArgumentException('Campaign donation badge requires campaign_id parameter');
        }
        
        // Check campaign_donations table for paid donations
        return $user->campaignDonations()
            ->where('campaign_id', $rules['campaign_id'])
            ->where('status', 'paid')
            ->exists();
    }

    /**
     * Check if user has donated the required total amount.
     */
    protected function checkTotalDonations(User $user, array $rules): bool
    {
        if (!isset($rules['amount'])) {
            throw new \InvalidArgumentException('Total donations badge requires amount parameter');
        }
        return $user->donations()->sum('amount') >= $rules['amount'];
    }

    /**
     * Check if user is a monthly donor.
     */
    protected function checkMonthlyDonor(User $user): bool
    {
        return $user->donations()->where('is_recurring', true)->exists();
    }

    /**
     * Check if user is a Carpoolear team member.
     */
    protected function checkCarpoolearMember(User $user): bool
    {
        // Hardcoded list of Carpoolear team member IDs
        $teamMemberIds = [3209, 3203];
        
        return in_array($user->id, $teamMemberIds);
    }
} 