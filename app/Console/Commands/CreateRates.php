<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Services\Logic\RatingManager; 

class CreateRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create rates from ending trips';

    protected $rateLogic;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(RatingManager $logic)
    {
        parent::__construct();
        $this->rateLogic = $logic;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("COMMAND CreateRates");
        $date = Carbon::now()->subDay()->toDateTimeString();
        $this->rateLogic->activeRatings($date);
    }
}
