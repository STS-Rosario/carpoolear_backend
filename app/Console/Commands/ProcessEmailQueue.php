<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class ProcessEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:process-emails 
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=3 : Number of times to attempt a job before logging it failed}
                            {--sleep=3 : Number of seconds to sleep when no job is available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the email queue with specific retry logic for email sending failures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting email queue processing...');
        
        // Process emails queue with specific configuration
        $this->call('queue:work', [
            'connection' => 'database',
            'queue' => 'emails',
            '--timeout' => $this->option('timeout'),
            '--tries' => $this->option('tries'),
            '--sleep' => $this->option('sleep'),
            '--verbose' => true
        ]);
        
        $this->info('Email queue processing completed.');
    }
}
