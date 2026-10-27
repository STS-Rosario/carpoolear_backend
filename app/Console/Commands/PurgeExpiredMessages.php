<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeExpiredMessages extends Command
{
    protected $signature = 'messages:purge-expired';

    protected $description = 'Delete messages older than one year';

    public function handle(): int
    {
        $cutoff = now()->subYear();
        $deletedCount = 0;

        DB::table('messages')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($messages) use (&$deletedCount) {
                $deletedCount += DB::table('messages')
                    ->whereIn('id', $messages->pluck('id'))
                    ->delete();
            });

        $this->info("Deleted {$deletedCount} messages older than one year.");

        return self::SUCCESS;
    }
}
