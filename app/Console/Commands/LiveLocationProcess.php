<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Services\Logic\TripLiveShareManager;

class LiveLocationProcess extends Command
{
    protected $signature = 'live-location:process';

    protected $description = 'Process live location stop reminders and auto-stops';

    public function __construct(protected TripLiveShareManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        \Log::info('COMMAND LiveLocationProcess');
        $this->manager->processActiveShares();

        return self::SUCCESS;
    }
}
