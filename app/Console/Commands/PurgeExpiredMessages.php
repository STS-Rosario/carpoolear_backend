<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeExpiredMessages extends Command
{
    protected $signature = 'messages:purge-expired
                            {--dry-run : Report the number of messages that would be deleted without deleting them}
                            {--limit=5000 : Maximum number of messages to delete in this run (default: 5000)}';

    protected $description = 'Delete messages older than one year';

    public function handle(): int
    {
        $cutoff = now()->subYear();
        $limitOption = $this->option('limit');
        $limit = null;
        if ($limitOption !== null) {
            $limit = filter_var($limitOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($limit === false) {
                $this->error('The --limit option must be a positive integer.');

                return self::FAILURE;
            }
        }

        $eligibleQuery = DB::table('messages')->where('created_at', '<', $cutoff);
        $eligibleCount = (clone $eligibleQuery)->count();
        $isDryRun = (bool) $this->option('dry-run');
        $deletedCount = 0;

        if (! $isDryRun) {
            while ($limit === null || $deletedCount < $limit) {
                $batchLimit = $limit === null
                    ? 500
                    : min(500, $limit - $deletedCount);
                $messageIds = (clone $eligibleQuery)
                    ->select('id')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->limit($batchLimit)
                    ->pluck('id');

                if ($messageIds->isEmpty()) {
                    break;
                }

                $deletedCount += DB::table('messages')->whereIn('id', $messageIds)->delete();
                usleep(100_000);
            }
        }

        $remainingCount = $isDryRun
            ? $eligibleCount
            : (clone $eligibleQuery)->count();
        $plannedCount = $isDryRun
            ? min($eligibleCount, $limit ?? $eligibleCount)
            : $deletedCount;
        $cutoffLabel = $cutoff->toIso8601String();

        $this->info(sprintf(
            '%s messages older than %s. Eligible: %d; planned: %d; deleted: %d; remaining: %d; limit: %s.',
            $isDryRun ? 'Would delete' : 'Deleted',
            $cutoffLabel,
            $eligibleCount,
            $plannedCount,
            $deletedCount,
            $remainingCount,
            $limit === null ? 'none' : $limit,
        ));

        Log::info('Expired messages purge completed', [
            'cutoff' => $cutoffLabel,
            'dry_run' => $isDryRun,
            'limit' => $limit,
            'eligible_count' => $eligibleCount,
            'planned_count' => $plannedCount,
            'deleted_count' => $deletedCount,
            'remaining_count' => $remainingCount,
        ]);

        return self::SUCCESS;
    }
}
