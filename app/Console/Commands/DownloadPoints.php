<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Models\Trip;
use STS\Services\GoogleDirection;

class DownloadPoints extends Command
{
    protected GoogleDirection $download;

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
     * @param  GoogleDirection|null  $download  Optional downloader (for tests); defaults to a new instance.
     */
    public function __construct(?GoogleDirection $download = null)
    {
        parent::__construct();
        $this->download = $download ?? new GoogleDirection;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info('COMMAND DownloadPoints');
        $trips = Trip::where('trip_date', '>=', Carbon::now()->toDateTimeString());
        // $trips->has('points', '=', 0);
        // $trips->limit(1);
        foreach ($trips->get() as $trip) {
            if ($trip->points->count() === 0) {
                $this->download->download($trip);
            }
        }
    }
}
