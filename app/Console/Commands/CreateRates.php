<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Contracts\Logic\IRateLogic;

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
    public function __construct(IRateLogic $logic)
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
        console_log('Running rating process');
        $date = Carbon::now()->subDay()->toDateString();
        $this->rateLogic->activeRatings($date);
    }
}
