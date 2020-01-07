<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use DB;
use STS\Entities\Trip;
use STS\Contracts\Repository\Trip as TripRepo;
use Illuminate\Console\Command;

class CleanTripVisibility extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:visibilityclean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean trip visibility';

    protected $tripLogic;
    protected $tripRepo;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(TripRepo $repo)
    {
        $this->tripRepo = $repo;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = Carbon::now();

        $trips = Trip::where('trip_date', '>=', $now->toDateTimeString())->where('friendship_type_id', '<', Trip::PRIVACY_PUBLIC);
        
        $trips = $trips->get();

        $ids = $trips->map(function ($trip) {
            return $trip->id;
        });

        DB::table('user_visibility_trip')->whereNotIn('trip_id', $ids)->delete();
    }
}
