<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use STS\Entities\Trip;
use Illuminate\Console\Command;
use STS\Services\GoogleDirection;

class DownloadPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download points';
 

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct()
    {
        parent::__construct();
        $this->download = new GoogleDirection();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $trips = Trip::where('trip_date', '>=', Carbon::now()->toDateTimeString());
        // $trips->has('points', '=', 0);
        // $trips->limit(1);
        foreach ($trips->get() as $trip) {
            if ($trip->points->count() == 0) {
                $this->download->download($trip);
            }
        }

    }
}
