<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\User;
use STS\Services\BadgeEvaluatorService;
use Illuminate\Support\Facades\Log;

class EvaluateBadges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'badges:evaluate 
                            {--user-ids= : Comma-separated list of specific user IDs to evaluate}
                            {--active-only : Only evaluate users with recent activity (last 30 days)}
                            {--activity-days=30 : Days to consider for active users (default: 30)}
                            {--batch-size=100 : Number of users to process in each batch}
                            {--dry-run : Show what would be evaluated without actually awarding badges}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate badges for users and award any that are earned';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting badge evaluation...');

        // Get user statistics
        $stats = $this->getUserStats();
        $this->displayUserStats($stats);

        if ($stats['total_users'] === 0) {
            $this->warn('No users found matching the criteria.');
            return 0;
        }

        // Confirm before proceeding (unless dry run)
        if (!$this->option('dry-run') && !$this->confirm('Proceed with badge evaluation?')) {
            $this->info('Badge evaluation cancelled.');
            return 0;
        }

        // Process users in batches
        $this->processUsersInBatches($stats);

        $this->info('Badge evaluation completed!');
        return 0;
    }

    /**
     * Get user statistics based on filters.
     */
    protected function getUserStats(): array
    {
        $query = User::query()
            ->where('active', true)
            ->where('banned', false);

        // Filter by specific user IDs
        if ($userIds = $this->option('user-ids')) {
            $ids = array_map('intval', explode(',', $userIds));
            $query->whereIn('id', $ids);
        }

        // Filter by activity (using last_connection field)
        if ($this->option('active-only')) {
            $days = (int) $this->option('activity-days');
            $query->where('last_connection', '>=', now()->subDays($days));
        }

        $totalUsers = $query->count();

        return [
            'total_users' => $totalUsers,
            'query' => $query
        ];
    }

    /**
     * Display user statistics.
     */
    protected function displayUserStats(array $stats): void
    {
        $this->info("User Statistics:");
        $this->line("  Total users to evaluate: {$stats['total_users']}");
        
        if ($this->option('user-ids')) {
            $this->line("  Filtering by specific user IDs");
        }
        
        if ($this->option('active-only')) {
            $days = $this->option('activity-days');
            $this->line("  Only users with recent connections (last {$days} days)");
        }
        
        if ($this->option('dry-run')) {
            $this->warn("  DRY RUN MODE - No badges will be awarded");
        }
        
        $this->line('');
    }

    /**
     * Process users in batches.
     */
    protected function processUsersInBatches(array $stats): void
    {
        $batchSize = (int) $this->option('batch-size');
        $query = $stats['query'];
        $totalUsers = $stats['total_users'];
        $processed = 0;
        $badgesAwarded = 0;
        $errors = 0;

        $this->info("Processing users in batches of {$batchSize}...");
        $this->line('');

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        $query->chunk($batchSize, function ($users) use (&$processed, &$badgesAwarded, &$errors, $progressBar) {
            foreach ($users as $user) {
                try {
                    if (!$this->option('dry-run')) {
                        $badgeEvaluator = new BadgeEvaluatorService();
                        $badgeEvaluator->evaluate($user);
                        
                        // Count newly awarded badges
                        $newBadges = $user->badges()->wherePivot('awarded_at', '>=', now()->subMinutes(5))->count();
                        $badgesAwarded += $newBadges;
                    }
                    
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Error evaluating badges for user', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->line('');

        // Display results
        $this->displayResults($processed, $badgesAwarded, $errors);
    }

    /**
     * Display final results.
     */
    protected function displayResults(int $processed, int $badgesAwarded, int $errors): void
    {
        $this->line('');
        $this->info('Evaluation Results:');
        $this->line("  Users processed: {$processed}");
        
        if (!$this->option('dry-run')) {
            $this->line("  Badges awarded: {$badgesAwarded}");
        }
        
        if ($errors > 0) {
            $this->error("  Errors encountered: {$errors}");
        }
        
        $this->line('');
    }
}
