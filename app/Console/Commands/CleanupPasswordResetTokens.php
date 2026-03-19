<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CleanupPasswordResetTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:cleanup-reset-tokens 
                            {--hours=24 : Number of hours after which to delete tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired password reset tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($hours);
        
        $this->info("Cleaning up password reset tokens older than {$hours} hours...");
        
        $deletedCount = DB::table('password_resets')
            ->where('created_at', '<', $cutoffTime)
            ->delete();
            
        $this->info("Deleted {$deletedCount} expired password reset tokens.");
        
        // Also clean up failed jobs related to email sending
        if (Schema::hasTable('failed_jobs')) {
            $failedEmailJobs = DB::table('failed_jobs')
                ->where('payload', 'like', '%SendPasswordResetEmail%')
                ->where('failed_at', '<', $cutoffTime)
                ->delete();

            if ($failedEmailJobs > 0) {
                $this->info("Also cleaned up {$failedEmailJobs} failed email jobs.");
            }
        }
    }
}
