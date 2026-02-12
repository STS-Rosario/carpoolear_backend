<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\Badge;
use STS\Models\CampaignDonation;
use STS\Models\UserBadge;

class AssignMsMjmsCampaignBadge extends Command
{
    protected $signature = 'badges:assign-msmjms-campaign
                            {--dry-run : Show what would be done without creating the badge or assigning user_badges}';

    protected $description = 'Create the +S+J+S campaign badge and assign it to users who donated (campaign_id=1, status=paid)';

    private const BADGE_SLUG = 'aportante-msmjms';
    private const CAMPAIGN_ID = 1;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be made.');
        }

        // 1. Create or get the badge
        $badgeData = [
            'title' => 'Aporté a la campaña +S+J+S',
            'slug' => self::BADGE_SLUG,
            'description' => 'Yo aporté para la campaña +Seguro +Justo +Simple',
            'image_path' => 'badges/msmjms.jpg',
            'rules' => ['campaignId' => self::CAMPAIGN_ID],
            'visible' => true,
        ];

        $badge = Badge::where('slug', self::BADGE_SLUG)->first();
        if ($badge) {
            $this->info("Badge already exists (id: {$badge->id}).");
            if (!$dryRun) {
                $badge->update($badgeData);
                $this->line('Badge data updated.');
            }
        } else {
            if ($dryRun) {
                $this->info('Would create badge: ' . $badgeData['title']);
            } else {
                $badge = Badge::create($badgeData);
                $this->info("Badge created (id: {$badge->id}).");
            }
        }

        $badgeId = $badge?->id;

        // 2. Users with paid donations for campaign_id = 1
        $donorUserIds = CampaignDonation::query()
            ->where('campaign_id', self::CAMPAIGN_ID)
            ->where('status', 'paid')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $total = $donorUserIds->count();
        $this->info("Found {$total} unique donor(s) with paid donations for campaign " . self::CAMPAIGN_ID . '.');

        if ($total === 0) {
            return 0;
        }

        // 3. Users who don't have this badge yet (skip when dry-run and badge not created)
        $toAssign = $donorUserIds;
        $skipped = 0;
        if ($badgeId !== null) {
            $alreadyHave = UserBadge::where('badge_id', $badgeId)
                ->whereIn('user_id', $donorUserIds->toArray())
                ->pluck('user_id')
                ->flip();
            $toAssign = $donorUserIds->reject(fn ($userId) => $alreadyHave->has($userId))->values();
            $skipped = $total - $toAssign->count();
        } elseif ($dryRun) {
            $this->line('(Badge not created yet; in dry-run all donors are listed as would-be assigned.)');
        }
        $toAssignCount = $toAssign->count();

        if ($skipped > 0) {
            $this->line("{$skipped} user(s) already have this badge.");
        }
        if ($toAssignCount === 0) {
            $this->info('No new user_badges to assign.');
            return 0;
        }

        $this->info("Assigning badge to {$toAssignCount} user(s).");

        if ($dryRun) {
            $this->table(
                ['user_id'],
                $toAssign->map(fn ($id) => [$id])->toArray()
            );
            $this->info('Dry run complete. Run without --dry-run to apply.');
            return 0;
        }

        $now = now();
        $inserts = $toAssign->map(fn ($userId) => [
            'user_id' => $userId,
            'badge_id' => $badgeId,
            'awarded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        UserBadge::insert($inserts);
        $this->info("Assigned badge to {$toAssignCount} user(s).");

        return 0;
    }
}
